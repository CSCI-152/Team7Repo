<?php
	$this->pageTitle("Dashboard");
?>

<h1 class="mb-3 p-5 text-white bg-blue instructordashboard">
	Instructor Dashboard</h1>

<button class = 'btn btn-secondary float-md-right text-white' data-start-tour="Instructor Dashboard Tour">Help</button><br><br> 
<div class="border mb-3 p-4 tbl-background d-flex flex-column flex-md-row justify-content-between align-items-center">
	<h4 class="mb-md-0">Add a new classroom section</h4>
	<a class="btn btn-secondary float-right text-white dashboardaddclass" href='<?php echo $this->baseUrl('/Instructor/AddClass') ?>'>Create a Section</a>
</div>

<?php $classes = InstructorClasses::find("instructorid =:0:", $user->id); ?>
<div class="table-responsive classes">
	<table class="table table-bordered tbl-background">
		<thead>
			<tr>
				<th scope="col" class="classtitle"> Class </th>
				<th scope="col" class="classdescription"> Description </th>
				<th scope="col" class="classmeetings"> Days/Times</th>
				<th scope="col" class="classstudents"> Student Enrollment</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach($classes as $class):?>
				<tr>
					<td class="classpage"><a href = '<?php echo $this->baseUrl("/Instructor/ViewClass/{$class->classid}") ?>'> <b><?php echo $class->class ?></b></a></td>
					<td><?php echo $class->description ?></td>
					<td><?php echo $class->getClassTimeString() ?></td>
					<td><?php $students = studentClasses::find("classId =:0:", $class->classid); echo count($students) ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div> 

<br> <br/>