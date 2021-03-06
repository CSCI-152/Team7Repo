<form method="POST">
	<?php if (count($errors)) : ?>
		<ul class="error-list">
		<?php foreach ($errors as $errorMsg) : ?>
			<li><?php echo $errorMsg ?></li>
		<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<!--Initialize form-->
  <br><br><br><br>

	<div class="form-row">
    <div class="form-group col-md-6">
      <label for="inputfirst">First Name</label>
	  <span class="text-danger">* <?php echo $firstError; ?> </span>
	  <!--Output the appropriate error message (if there is one)-->
	  <input type="text" name = "first" class="form-control" id="inputfirst" placeholder="First">
	  <!--Input for first name is text entry stored as 'first'-->
    </div>
    <div class="form-group col-md-6">
      <label for="inputlast">Last Name</label>
	  <span class="text-danger">* <?php echo $lastError; ?> </span>
	  <!--Output the appropriate error message (if there is one)-->
	  <input type="text" name = "last" class="form-control" id="inputlast" placeholder="Last">
	  <!--Input for last name is text entry stored as 'last'-->
    </div>
  </div>
  <div class="form-group">
    <label for="inputemail">Email</label>
	<span class="text-danger">* <?php echo $emailError; ?> </span>
	<!--Output the appropriate error message (if there is one)-->
    <input type="text" name = "email" class="form-control" id="inputemail" placeholder="user@csufresno.edu">
	<!--Input for email is text entry stored as 'email'-->
  </div>
  <div class="form-group">
	<!--Input for password is of type password stored as 'pass'-->
    <label for="inputpass">Password</label>
	<span class="text-danger">* <?php echo $passError; ?> </span>
	<!--Output the appropriate error message (if there is one)-->
	<input type="password" name = "pass" class="form-control" id="inputpass">
	<!--Type password allows for the password to be hidden-->
  </div>		

	<br>
	<input type="submit" name="submit" value="Submit">
	<!--Create a submit button to complete the form-->
</form>

