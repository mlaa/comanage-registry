<?php
/**
 * COmanage Registry Identifier Enroller Controller
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
 * @package       registry-plugin
 * @since         COmanage Registry v2.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

App::uses('CoPetitionsController', 'Controller');

class IdentifierEnrollerCoPetitionsController extends CoPetitionsController {
  // Class name, used by Cake
  public $name = "IdentifierEnrollerCoPetitions";

  public $uses = array("CoPetition");
  
  /**
   * Collect additional identifiers
   *
   * @since  COmanage Registry v2.0.0
   * @param  Integer $id       CO Petition ID
   * @param  Array   $onFinish Redirect target on completion
   * @todo   Replace this with CO-1002
   */
  
  protected function execute_plugin_collectIdentifier($id, $onFinish) {
    // First pull the EF attributes
    
    $efId = $this->CoPetition->field('co_enrollment_flow_id', array('CoPetition.id' => $id));
    
    $args = array();
    $args['conditions']['CoEnrollmentAttribute.co_enrollment_flow_id'] = $efId;
    // Using NotPermitted allows us to leverage the existing configuration
    // in a non-intrusive way
    $args['conditions']['CoEnrollmentAttribute.required'] = RequiredEnum::NotPermitted;
    $args['conditions']['CoEnrollmentAttribute.attribute LIKE'] = 'p:identifier:%';
    $args['order'] = 'CoEnrollmentAttribute.ordr ASC';
    $args['contain'] = false;
    
    $attrs = $this->CoPetition->CoEnrollmentFlow->CoEnrollmentAttribute->find('all', $args);
    
    if(!empty($attrs)) {
      // We have some attributes, render a form
      $this->set('vv_identifiers', $attrs);
      
      if($this->request->is('post')) {
        // Post, process the request
        
        // Get the CO Person ID
        $coPersonId = $this->CoPetition->field('enrollee_co_person_id', array('CoPetition.id' => $id));

        // Walk through the list of configured identifiers and save any we find.
        // (While the form enforces "required", we don't bother here -- it's not even clear if we should.)
        
        // Run everything in a single transaction. If any identifier fails to save,
        // we want the form to rerender, and the easiest thing is to make all
        // identifiers editable (rather than just whichever failed).
        
        $dbc = $this->CoPetition->EnrolleeCoPerson->Identifier->getDataSource();
        $dbc->begin();
        
        $err = false;
        
        foreach($attrs as $attr) {
          // This should be of the form p:identifier:TYPE
          $a = explode(':', $attr['CoEnrollmentAttribute']['attribute'], 3);
          
          if(!empty($a[2])
             // For simplicity in form management, the identifiers are submitted under 'CoPetition'
             && !empty($this->request->data['CoPetition'][ $attr['CoEnrollmentAttribute']['id'] ])) {
            // We have the type and the proposed identifier
            
            $identifier = array(
              'Identifier' => array(
                'identifier'   => $this->request->data['CoPetition'][ $attr['CoEnrollmentAttribute']['id'] ],
                'type'         => $a[2],
                'login'        => false,
                'co_person_id' => $coPersonId,
                'status'       => SuspendableStatusEnum::Active
              )
            );
            
            try {
              $this->CoPetition->EnrolleeCoPerson->Identifier->create();
              $this->CoPetition->EnrolleeCoPerson->Identifier->save($identifier, array('provision' => false));
              
              // Create some history
              $actorCoPersonId = $this->Session->read('Auth.User.co_person_id');
              
              $txt = _txt('pl.identifierenroller.selected',
                          array($this->request->data['CoPetition'][ $attr['CoEnrollmentAttribute']['id'] ],
                                $a[2]));
              
              $this->CoPetition->EnrolleeCoPerson->HistoryRecord->record($coPersonId,
                                                                         null,
                                                                         null,
                                                                         $actorCoPersonId,
                                                                         ActionEnum::CoPersonEditedManual,
                                                                         $txt);

              $this->CoPetition->CoPetitionHistoryRecord->record($id,
                                                                 $actorCoPersonId,
                                                                 PetitionActionEnum::AttributesUpdated,
                                                                 $txt);
            }
            catch(Exception $e) {
              $dbc->rollback();
              $err = true;
              $this->Flash->set($e->getMessage(), array('key' => 'error'));
              break;
            }
          }
        }
        
        if(!$err) {
          // We're done, commit and redirect
          $dbc->commit();
          $this->redirect($onFinish);
        }
        // else fall through to let the error and form render
      }
      // else nothing to do, just let the form render
    } else {
      // There are no attributes to collect, redirect
      
      $this->redirect($onFinish);
    }
  }
}
