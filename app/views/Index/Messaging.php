<?php
$currentUser = User::getCurrentUser();

$conversations = ChatConversation::find("FIND_IN_SET(:0:, conversations.users)", $currentUser->id);
$conversationUsers = [];
$conversations = array_map(function(ChatConversation $chatconversation) use (&$conversationUsers) {
	$conversationUsers = array_merge($conversationUsers, $chatconversation->users);

	return [
		'id' => $chatconversation->id,
		'users' => $chatconversation->users
	];
}, $conversations);

// Student -> classes -> instructors, tas, and students, admins
// instructor -> classes -> students and tas, admins
// admin -> all users
if ($currentUser->type == 'admin') {
	$contacts = User::find();
}
else {
	$conversationUsers = array_unique($conversationUsers);

	if ($currentUser->type == 'instructor') {
		// Select classes from instructor id
		$sql = "
			SELECT
				ic.*
			FROM
				instructorclasses as ic
			WHERE
				ic.instructor_id = :0:
		";
	}
	else {
		// Select classes from student id
		$sql = "
			SELECT
				ic.*
			FROM
				instructorclasses as ic
			INNER JOIN classes as c ON
				c.class_id = ic.class_id
			WHERE
				c.student_id = :0:
		";
	}

	// Select all related users from classes
	$sql = "
		SELECT
			u.*
		FROM
			({$sql}) as ic
		INNER JOIN classes as c ON
			c.class_id = ic.class_id
		INNER JOIN users as u ON
			u.id = ic.instructor_id OR
			u.id = ic.ta_id OR
			u.id = c.student_id OR
			u.id IN :1:
		WHERE
			u.id <> :0: AND
			u.type <> 'admin'
		GROUP BY
			u.id

		UNION

		SELECT
			u.*
		FROM
			users as u
		WHERE
			u.type = 'admin'
	";

	$contacts = User::query(
		$sql, $currentUser->id,
		$conversationUsers ?: [0]
	);
}

$contacts = array_map(function($user) {
	return [
		'id' => $user->id,
		'firstName' => $user->firstName,
		'lastName' => $user->lastName,
		'fullName' => $user->getFullName(),
		'type' => $user->type
	];
}, $contacts);

$currentUser = User::getCurrentUser();

$this->scriptEnqueue('vuejs', 'https://cdn.jsdelivr.net/npm/vue@2.6.12');
VueLoader::render([
	APP_ROOT . '/app/vue-components/chat-form.vue',
	APP_ROOT . '/app/vue-components/chat-bubble.vue'
], '#messaging-app');
?>

<script>
	var contactList = <?= json_encode($contacts) ?>;
	var conversationList = <?= json_encode($conversations) ?>;
</script>
<button class = 'btn btn-secondary float-md-right text-white' data-start-tour="Direct Messaging Tour">Help</button><br><br> 
<div class = "dmgeneral">
<div id="messaging-app">
	<chat-form></chat-form>
</div>
</div>