<div class="alert alert-danger alert-dismissible fade show pt-2">
	<?php 
		$now = time(); // or your date as well
		$your_date = strtotime("2019-04-01");
		$datediff =  $your_date - $now;

		$remaining = round($datediff / (60 * 60 * 24));
	?>
	<strong>Emergency note :</strong> Please download Your Uploaded Answer Sheets / Reviewed Copy of Your Answer Sheets of any test from any subject for future refrence (if you want). We will remove all these Sheets within <?php echo $remaining; ?> Day.
</div>