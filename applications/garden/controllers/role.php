<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Mark O'Sullivan
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Mark O'Sullivan at mark [at] lussumo [dot] com
*/

/**
 * RBAC (Role Based Access Control)
 */
class RoleController extends GardenController {
   
   public $Uses = array('Database', 'Form', 'RoleModel');
   
   public function Add() {
      $this->Permission('Garden.Roles.Manage');
      // Use the edit form with no roleid specified.
      $this->View = 'Edit';
      $this->Edit();
   }
   
   public function Delete($RoleID = FALSE) {
      $this->Permission('Garden.Roles.Manage');
      $this->AddSideMenu('garden/role');
      
      $Role = $this->RoleModel->GetByRoleID($RoleID);
      if ($Role->Deletable == '0')
         $this->Form->AddError('You cannot delete this role.');
      
      // Make sure the form knows which item we are deleting.
      $this->Form->AddHidden('RoleID', $RoleID);
      
      // Figure out how many users will be affected by this deletion
      $this->AffectedUsers = $this->RoleModel->GetUserCount($RoleID);
      
      // Figure out how many users will be orphaned by this deletion
      $this->OrphanedUsers = $this->RoleModel->GetUserCount($RoleID, TRUE);

      // Get a list of roles other than this one that can act as a replacement
      $this->ReplacementRoles = $this->RoleModel->GetByNotRoleID($RoleID);
      
      if ($this->Form->AuthenticatedPostBack()) {
         // Make sure that a replacement role has been selected if there were going to be orphaned users
         if ($this->OrphanedUsers > 0) {
            $Validation = new Gdn_Validation();
            $Validation->ApplyRule('ReplacementRoleID', 'Required', 'You must choose a replacement role for orphaned users.');
            $Validation->Validate($this->Form->FormValues());
            $this->Form->SetValidationResults($Validation->Results());
         }
         if ($this->Form->ErrorCount() == 0) {
            // Go ahead and delete the Role
            $this->RoleModel->Delete($RoleID, $this->Form->GetValue('ReplacementRoleID'));
            $this->RedirectUrl = Url('garden/role');
            $this->StatusMessage = Gdn::Translate('Deleting role...');
         }
      }
      $this->Render();
   }
   
   public $HasJunctionPermissionData;
   public function Edit($RoleID = FALSE) {
      $this->Permission('Garden.Roles.Manage');
      $this->AddSideMenu('garden/role');
      $PermissionModel = new PermissionModel();
      $this->Role = $this->RoleModel->GetByRoleID($RoleID);
      // $this->EditablePermissions = is_object($this->Role) ? $this->Role->EditablePermissions : '1';
      if ($this->Head)
         $this->Head->AddScript('/js/library/jquery.gardencheckboxgrid.js');
      
      // Set the model on the form.
      $this->Form->SetModel($this->RoleModel);
      
      // Make sure the form knows which item we are editing.
      $this->Form->AddHidden('RoleID', $RoleID);
      
      $LimitToSuffix = !$this->Role || $this->Role->CanSession == '1' ? '' : 'View';
      
      // Load all non-junction permissions based on enabled applications and plugins
      $this->PermissionData = $PermissionModel->GetPermissions($LimitToSuffix);
      
      // Define all junction tables
      $JunctionTables = $PermissionModel->GetJunctionTables();
         
      // Define all of the junction rows/permissions
      $this->JunctionTableData = array();
      $this->HasJunctionPermissionData = FALSE;
      foreach ($JunctionTables->Result() as $Table) {
         // Load all junction table rows (these will represent the checkbox group name)
         $this->JunctionTableData[$Table->JunctionTable]['Rows'] = $PermissionModel->GetJunctionData($Table->JunctionTable, $Table->JunctionColumn);
            
         // Load all available junction permissions
         $JunctionPermissionData = $PermissionModel->GetJunctionPermissions($Table->JunctionTable, $LimitToSuffix);
         $this->JunctionTableData[$Table->JunctionTable]['Permissions'] = $JunctionPermissionData;
         if ($JunctionPermissionData->NumRows() > 0)
            $this->HasJunctionPermissionData = TRUE;
      }
      
      // Initialize the permission data containers
      $this->RolePermissionData = FALSE;
      $this->RoleJunctionPermissionData = FALSE;

      // If seeing the form for the first time...
      if ($this->Form->AuthenticatedPostBack() === FALSE) {
         // Get the role data for the requested $RoleID and put it into the form.
         if ($RoleID > 0 // && $this->EditablePermissions
             ) {
            $this->RolePermissionData = $this->RoleModel->GetPermissions($RoleID);
            $this->RoleJunctionPermissionData = $this->RoleModel->GetJunctionPermissionsForRole($RoleID);
         }
            
         $this->Form->SetData($this->Role);
      } else {
         // If the form has been posted back...
         // 2. Save the data (validation occurs within):
         if ($this->Form->Save()) {
            $this->StatusMessage = Gdn::Translate('Your changes have been saved.');
            // TODO - redirect after save?
            // $this->RedirectUrl = Url('/role/');
         }
         $this->RolePermissionData = $this->Form->GetFormValue('PermissionID');
         $this->RoleJunctionPermissionData = $this->Form->GetFormValue('JunctionPermissionID');
         if (!is_array($this->RoleJunctionPermissionData))
            $this->RoleJunctionPermissionData = array();
      }
      
      $this->Render();
   }
      
   public function Index() {
      $this->Permission('Garden.Roles.Manage');
      $this->AddSideMenu('garden/role');
      if ($this->Head) {
         $this->Head->AddScript('/js/library/jquery.tablednd.js');
         $this->Head->AddScript('/js/library/jquery.ui.packed.js');
      }
      $this->RoleData = $this->RoleModel->Get();
      $this->Render();
   }
   
   public function Initialize() {
      parent::Initialize();
      if ($this->Menu)
         $this->Menu->HighlightRoute('/garden/settings');
   }
}