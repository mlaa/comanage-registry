<!--
  /*
   * COmanage Gears Organizational Person Add via LDAP View
   *
   * Version: $Revision$
   * Date: $Date$
   *
   * Copyright (C) 2010-2011 University Corporation for Advanced Internet Development, Inc.
   * 
   * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
   * the License. You may obtain a copy of the License at
   * 
   * http://www.apache.org/licenses/LICENSE-2.0
   * 
   * Unless required by applicable law or agreed to in writing, software distributed under
   * the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
   * KIND, either express or implied. See the License for the specific language governing
   * permissions and limitations under the License.
   *
   */
-->
<h1 class="ui-state-default">Add a New Organizational Person</h1>

<?php
  // XXX this page needs I18N and maybe sanitize_html

  // Assemble list of organizations
  $r = array();
  
  foreach($organizations as $o)
  {
    $i = $o['Organization']['id'];
    $n = $o['Organization']['name'];
    
    $r[$i] = $n;
  }

  $submit_label = "Add New Person";
  echo $this->Form->create('OrgPerson',
                           array('action' => 'selectvialdap',
                                 'inputDefaults' => array('label' => false, 'div' => false)));
?>

<table id="add_org_person_via_ldap" class="ui-widget">
  <tbody>
    <tr class="line1">
      <td>
        Organization<font class="required">*</font>
      </td>
      <td>
        <?php echo $this->Form->select('organization', $r); ?>
      </td>
    </tr>
    <tr class="line2">
      <td>
        Surname <!-- XXX allow additional/configurable search terms -->
      </td>
      <td>
        <?php echo $this->Form->input('sn'); ?>
      </td>
    </tr>
    <tr>
      <td>
        <i><font class="required">* denotes required field</font></i><br />
      </td>
      <td>
        <?php echo $this->Form->submit('Search'); ?>
      </td>
    </tr>
  </tbody>
</table>

<?php
  echo $this->Form->end();
?>