<?php
   //assuming $this->user is null or the screen we want to edit
   $user = $this->user;
   if(!is_numeric($user->id))
      $user->allow_email=1;
?>
<!-- Begin User Form -->
	<div>
     <table class='edit_win' cellpadding='6' cellspacing='0'>
       <tr> 
         <td class='firstrow'><h5>Full Name</h5></td>
         <td class='edit_col firstrow'>
           <input type="text" id="name" name="user[name]" value="<?=$user->name?>">
         </td>
       </tr>
       <tr>
         <td><h5>Email</h5></td>
         <td>
           <input type="text" id="width" name="user[email]" value="<?=$user->email?>">
         </td>
       </tr>
       <tr>
         <td><h5>System Notifications</h5></td>
         <td>
           <input type="checkbox" id="allow_email" value="allow" name="user[allow_email]"<? if($user->allow_email) echo " CHECKED"?>> Yes, I want to recieve e-mail notices about system activity that concerns me (recommended).
         </td>         
       <? if (isLoggedIn() && $_SESSION['user']->username != $user->username) { ?>
       <tr>
         <td><h5>Username (RCS ID)</h5></td>
         <td>
           <input type="text" id="username" name="user[username]" value="<?=$user->username?>">
         </td>
       </tr>
       <tr>
         <td><h5>Admin Privileges</h5></td>
         <td>
           <input type="checkbox" id="admin_privileges" value="admin" name="user[admin_privileges]"<? if($user->admin_privileges) echo " CHECKED"?>>
         </td>
       </tr>
       <? } else {?>
       <tr>
         <td><h5>Username (RCS ID)</h5></td>
         <td>
           <?=$user->username ?>
         </td>
       </tr>
       <? } ?>
     </table>
     </div>
	<br clear="all" />
<!-- End Screen Form General Section -->