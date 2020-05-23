<?php
/**
 * COmanage Registry Ad Hoc Attributes Controller
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v3.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

App::uses("MVPAController", "Controller");

class AdHocAttributesController extends MVPAController {
  // Class name, used by Cake
  public $name = "AdHocAttributes";
  
  // Establish pagination parameters for HTML views
  public $paginate = array(
    'limit' => 25,
    'order' => array(
      'tag' => 'asc'
    )
  );

  public $edit_contains = array(
    'CoDepartment',
    'CoPersonRole' => array('CoPerson' => 'PrimaryName'),
    'OrgIdentity' => array('PrimaryName')
  );

  public $view_contains = array(
    'CoDepartment',
    'CoPersonRole' => array('CoPerson' => 'PrimaryName'),
    'OrgIdentity' => array('OrgIdentitySourceRecord' => array('OrgIdentitySource'),
                           'PrimaryName'),
    'SourceAdHocAttribute'
  );
  
  /**
   * Authorization for this Controller, called by Auth component
   * - precondition: Session.Auth holds data used for authz decisions
   * - postcondition: $permissions set with calculated permissions
   *
   * @since  COmanage Registry v3.3.0
   * @return Array Permissions
   */
  
  function isAuthorized() {
    $roles = $this->Role->calculateCMRoles();
    $pids = $this->parsePersonID($this->request->data);
    
    // Is this a read only record? True if it belongs to an Org Identity that has
    // an OrgIdentity Source Record. As of the initial implementation, not even
    // CMP admins can edit such a record.
    
    $readOnly = false;
    
    if($this->action == 'edit' && !empty($this->request->params['pass'][0])) {
      $sourceAttributeId = (bool)$this->AdHocAttribute->field('source_ad_hoc_attribute_id', array('id' => $this->request->params['pass'][0]));
      
      if($sourceAttributeId) {
        $readOnly = true;
      } else {
        $orgIdentityId = $this->AdHocAttribute->field('org_identity_id', array('id' => $this->request->params['pass'][0]));
        
        if($orgIdentityId) {
          $readOnly = $this->AdHocAttribute->OrgIdentity->readOnly($orgIdentityId);
        }
      }
    }
    
    if($readOnly) {
      // Proactively redirect to view. This will also prevent (eg) the REST API
      // from editing a read only record.
      $args = array(
        'controller' => 'ad_hoc_attributes',
        'action'     => 'view',
        filter_var($this->request->params['pass'][0],FILTER_SANITIZE_SPECIAL_CHARS)
      );
      
      $this->redirect($args);
    }
    
    // In order to manipulate an telephone number, the authenticated user must have permission
    // over the associated Org Identity or CO Person Role. For add action, we accept
    // the identifier passed in the URL, otherwise we lookup based on the record ID.
    
    $managed = false;
    $self = false;
    $number = null;
    
    if(!empty($roles['copersonid'])) {
      switch($this->action) {
      case 'add':
        if(!empty($pids['copersonroleid'])) {
          $managed = $this->Role->isCoOrCouAdminForCoPersonRole($roles['copersonid'],
                                                                $pids['copersonroleid']);
          
          // Map the requested CO Person Role ID to its CO Person ID
          $reqCoPersonId = $this->AdHocAttribute->CoPersonRole->field('co_person_id', array('id' => $pids['copersonroleid']));
          
          if($reqCoPersonId == $roles['copersonid']) {
            $self = true;
          }
        } elseif(!empty($pids['orgidentityid'])) {
          $managed = $this->Role->isCoOrCouAdminForOrgIdentity($roles['copersonid'],
                                                               $pids['orgidentityid']);
        }
        break;
      case 'delete':
      case 'edit':
      case 'view':
        if(!empty($this->request->params['pass'][0])) {
          // look up $this->request->params['pass'][0] and find the appropriate co person role id or org identity id
          // then pass that to $this->Role->isXXX
          $args = array();
          $args['conditions']['AdHocAttribute.id'] = $this->request->params['pass'][0];
          $args['contain'][] = 'CoPersonRole';
          
          $aha = $this->AdHocAttribute->find('first', $args);
          
          if(!empty($aha['AdHocAttribute']['co_person_role_id'])) {
            $managed = $this->Role->isCoOrCouAdminForCoPersonRole($roles['copersonid'],
                                                                  $aha['AdHocAttribute']['co_person_role_id']);
            
            if($aha['CoPersonRole']['co_person_id'] == $roles['copersonid']) {
              $self = true;
            }
          } elseif(!empty($aha['AdHocAttribute']['org_identity_id'])) {
            $managed = $this->Role->isCoOrCouAdminForOrgidentity($roles['copersonid'],
                                                                 $aha['AdHocAttribute']['org_identity_id']);
          }
        }
        break;
      }
    }
    
    // Construct the permission set for this user, which will also be passed to the view.
    $p = array();
    
    /* XXX No self service for ad hoc attributes, maybe later?
    // Self service is a bit complicated because permission can vary by type.
    // Self service only applies to CO Person-attached attributes.
    
    $selfperms = array(
      'add'    => false,
      'delete' => false,
      'edit'   => false,
      'view'   => false
    );
    
    if($self) {
      foreach(array_keys($selfperms) as $a) {
        $selfperms[$a] = $this->AdHocAttribute
                              ->CoPersonRole
                              ->CoPerson
                              ->Co
                              ->CoSelfServicePermission
                              ->calculatePermission($this->cur_co['Co']['id'],
                                                    'AdHocAttribute',
                                                    $a,
                                                    ($a != 'add' && !empty($aha['AdHocAttribute']['type']))
                                                     ? $aha['AdHocAttribute']['type'] : null);
      }
      
      $p['selfsvc'] = $this->Co->CoSelfServicePermission->findPermissions($this->cur_co['Co']['id']);
    } else {
      $p['selfsvc'] = null;
    }*/
    
    // Add a new Ad Hoc Attribute?
    $p['add'] = ($roles['cmadmin']
                 || ($managed && ($roles['coadmin'] || $roles['couadmin'])));
    
    // Delete an existing Ad Hoc Attribute?
    $p['delete'] = ($roles['cmadmin']
                    || ($managed && ($roles['coadmin'] || $roles['couadmin'])));
    
    // Edit an existing Ad Hoc Attribute?
    $p['edit'] = ($roles['cmadmin']
                  || ($managed && ($roles['coadmin'] || $roles['couadmin'])));
    
    // View all existing Ad Hoc Attributes?
    // Currently only supported via REST since there's no use case for viewing all
    $p['index'] = $this->request->is('restful') && ($roles['cmadmin'] || $roles['coadmin']);
    
    // View an existing Ad Hoc Attribute?
    $p['view'] = ($roles['cmadmin']
                  || ($managed && ($roles['coadmin'] || $roles['couadmin'])));
    
    $this->set('permissions', $p);
    return $p[$this->action];
  }
}
