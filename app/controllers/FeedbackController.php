<?php
class FeedbackController extends PermsController {
    
    public function FeedbackFormAction($classId = 0) {
		if (!$classId || !$this->request->isPost()) {
			return $this->redirect($this->viewHelpers->baseUrl());
		}

		$class = InstructorClasses::getByKey($classId);
		if (!$class) {
			return $this->json(['errors' => ['classid' => 'Given class id is invalid']]);
		}

		// Excrutiatingly exhaustive validation of the input data
		$errors = [];

		$requiredFields = [
			'title' => 'Title is required',
			'start' => 'Start time is required',
			'end' => 'End time is required',
			'fields' => 'Fields are required'
		];

		foreach ($requiredFields as $postField => $errorMsg) {
			if (empty($_POST[$postField]) || !trim($_POST[$postField])) {	// Must be set and not just whitespace
				$errors[$postField] = $errorMsg;
			}
		}

		if (!count($errors)) {
			// Basics data is here... advanced checking
			$timeRegex = '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/';

			foreach (['start', 'end'] as $timeField) {
				if (!preg_match($timeRegex, $_POST[$timeField])) {
					$errors[$timeField] = ucfirst($timeField) . ' time does not match the required format (HH:MM)';
				}
			}

			if (strcmp($_POST['start'], $_POST['end']) >= 0) {
				$errors['end'] = 'End time should be after start time';
			}

			// Verify form fields are good
			$fieldsErrorMsg = 'Fields JSON should be an array of at least one field objects';
			$fields = json_decode($_POST['fields'], true);
			if ($fields == null) {
				// Bad json
				$errors['fields'] = $fieldsErrorMsg;
			}
			elseif (!is_array($fields)) {
				// Should have been an array
				$errors['fields'] = $fieldsErrorMsg;
			}
			elseif (count($fields) < 1) {
				// Need at least one field
				$errors['fields'] = $fieldsErrorMsg;
			}
			else {
				// Verify field keys exist
				foreach ($fields as $fieldNdx => $field) {	// Ignoring fields keys, I don't care about them anyways
					if (!is_array($field)) {
						$errors['fields'] = $fieldsErrorMsg;
						break;
					}

					foreach (['type', 'label'] as $fieldProp) {
						if (empty($field[$fieldProp]) || !is_string($field[$fieldProp])) {
							$errors['fields'] = $fieldsErrorMsg;
							break 2;
						}
					}

					if (!isset($errors['fields'])) {
						$type = $field['type'];

						// Verify type is one of the enum values
						try {
							$fields[$fieldNdx]['type'] = FormFieldTypeEnum::$type();
						}
						catch (Exception $e) {
							$errors['fields'] = $fieldsErrorMsg;	// Invalid field type enum
							break;
						}

						if ($type == FormFieldTypeEnum::CHECKBOX_GROUP() || $type == FormFieldTypeEnum::RADIO_GROUP()) {
							// Verify options is set for required field types
							if (!isset($field['options']) || !is_array($field['options'])) {
								$errors['fields'] = $fieldsErrorMsg;
								break;
							}

							$allStrings = array_reduce($field['options'], function($carry, $option) {
								return $carry && is_string($option);
							}, true);

							if (!$allStrings) {
								$errors['fields'] = $fieldsErrorMsg;
								break;
							}
						}
					}
				}
			}

			if (!count($errors)) {
				// Still no errors... then we're golden!

				/** @var Db */
				$db = $this->get('Db');
				$db->startTransaction();

				$model = new FeedbackSession();
				$model->title = $_POST['title'];
				$model->classid = $class->classid;
				$model->startTime = date('Y-m-d ') . $_POST['start'] . ':00';
				$model->endTime = date('Y-m-d ') . $_POST['end'] . ':00';
				$model->createdDate = date('Y-m-d H:m:i');
				$model->isQuiz = isset($_POST['isQuiz'])
					? !!$_POST['isQuiz']
					: false;

				if ($model->save()) {
					// Save fields
					foreach ($fields as $field) {
						$fieldModel = new FeedbackSessionField($field);
						$fieldModel->feedbackSessionId = $model->id;
						if ($model->isQuiz) {
							$fieldModel->answer = $field['answer'];
						}

						if (!$fieldModel->save()) {
							$errors['_form'] = 'Unable to save the form';
							break;
						}
					}

					if (!count($errors)) {
						$db->commitTransaction();
						return $this->json(['success' => true]);
					}
					else {
						$db->abortTransaction();
					}
				}
				else {
					$errors['_form'] = 'There was a problem saving the record';
				}
			}
		}

		return $this->json(['errors' => $errors]);
    }
    
    public function PublishedFeedbackAction($classid, $viewAll = false) {
		$viewAll = !!$viewAll;
		$class = InstructorClasses::getByKey($classid);

		if (!$class) {
			return $this->redirect($this->viewHelpers->baseUrl());
		}

		$query = "
			SELECT
				fs.*
			FROM
				feedback_sessions as fs
			INNER JOIN instructorclasses as ic ON
				fs.class_id = ic.class_id
			WHERE
				fs.class_id = :0: AND
				fs.is_quiz = 0
		";

		if (!$viewAll) {
			$query .= "AND
				fs.start_time <= NOW() AND
				fs.end_time > NOW()
			";
		}

		
		$feedBackSessions = FeedbackSession::query($query, $classid);
		
		return $this->view([
			'feedbackSessions' => $feedBackSessions,
			'class' => $class,
			'viewAll' => $viewAll
		]);
	}

	public function PublishedQuizzesAction($classid) {
		$class = InstructorClasses::getByKey($classid);

		if (!$class) {
			return $this->redirect($this->viewHelpers->baseUrl());
		}

		$query = "
			SELECT
				fs.*
			FROM
				feedback_sessions as fs
			INNER JOIN instructorclasses as ic ON
				fs.class_id = ic.class_id
			WHERE
				fs.class_id = :0: AND
				fs.is_quiz = 1
		";
		
		$feedBackSessions = FeedbackSession::query($query, $classid);
		
		return $this->view([
			'feedbackSessions' => $feedBackSessions,
			'class' => $class
		]);
	}
	
	public function ResponseAction($feedbackid) { 
		$errors = [];

		$feedbackSession = FeedbackSession::findOne('id = :0: AND start_time <= NOW() AND end_time > NOW()', $feedbackid);
		if (!$feedbackSession) {
			SimpleAlert::error('This feedback session is no longer available');
			return $this->redirect($this->viewHelpers->baseUrl());	// TODO: Redirect to the class or something
		}

		/** @var FeedbackSessionField[] */
		$fields = [];
		foreach (FeedbackSessionField::find('feedback_session_id = :0:', $feedbackid) as $field) {
			$fields[$field->id] = $field;
		}

		if($this->request->isPost()) {
			$response = [];

			foreach ($fields as $field) {
				$fieldName = "field{$field->id}";

				if ($field->type == FormFieldTypeEnum::CHECKBOX_GROUP()) {
					$fieldResponse = $_POST[$fieldName] ?? [];
					if (!is_array($fieldResponse)) {
						$errors[$fieldName] = 'Format is invalid';
						continue;
					}

					foreach ($fieldResponse as $key => $val) {
						if (!is_numeric($key) || !is_string($val)) {
							$errors[$fieldName] = 'Format is invalid';
							continue 2;
						}
					}

					$response[$field->id] = [];
					foreach ($field->options as $ndx => $label) {
						$response[$field->id][] = in_array($ndx, $fieldResponse)
							? 1
							: 0;
					}
					$response[$field->id] = implode(',', $response[$field->id]);
				}
				else {
					$fieldResponse = $_POST[$fieldName] ?? '';
					if (!is_string($fieldResponse)) {
						$errors[$fieldName] = 'Format is invalid';
						continue;
					}

					$fieldResponse = trim($fieldResponse);
					if (!strlen($fieldResponse)) {
						if (!$field->optional) {
							$errors[$fieldName] = 'This field is required';
						}

						continue;
					}

					$response[$field->id] = $fieldResponse;
				}
			}

			if (!count($errors)) {
				$currentUser = User::getCurrentUser();
				/** @var Db */
				$db = $this->get('Db');
				$db->startTransaction();

				$feedbackResponse = new FeedbackResponse();
				$feedbackResponse->feedbackSessionId = $feedbackSession->id;
				$feedbackResponse->studentid = $currentUser->id;
				$feedbackResponse->createdDate = date('Y-m-d H:i:s');
				
				if ($feedbackResponse->save()) {
					foreach ($response as $fieldId => $fieldValue) {
						$fieldResponse = new FeedbackResponseField();
						$fieldResponse->feedbackResponseId = $feedbackResponse->id;
						$fieldResponse->feedbackSessionFieldId = $fieldId;
						$fieldResponse->response = $fieldValue;

						if (!$fieldResponse->save()) {
							$errors['_form'] = 'Unable to save the form';
							break;
						}
					}

					if (!count($errors)) {
						$db->commitTransaction();
						
						if ($feedbackSession->isQuiz) {
							SimpleAlert::success('<i class="far fa-grin-beam"></i> Quiz response recorded successfully', true);
							return $this->redirect($this->viewHelpers->baseUrl("/Feedback/PublishedQuizzes/{$feedbackSession->classid}"));
						}
						else {
							SimpleAlert::success('<i class="far fa-grin-beam"></i> Feedback recorded successfully', true);
							return $this->redirect($this->viewHelpers->baseUrl("/Feedback/PublishedFeedback/{$feedbackSession->classid}"));
						}
					}
					else {
						$db->abortTransaction();
					}
				}
				else {
					$errors['_form'] = 'Unable to save your response';
				}
			}
		}

		return $this->view(['errors' => $errors, 'fields' => $fields]);
	}
	
   public function InstructorResultAction($feedbackid = 0) {
		$feedbackSession = FeedbackSession::getByKey($feedbackid);
		if (!$feedbackSession) {
			SimpleAlert::warning('The feedback session does not exist');
			return $this->redirect($this->viewHelpers->baseUrl());
		}

		/** @var Db */
		$db = $this->get('Db');
		$fields = [];
		foreach (FeedbackSessionField::find('feedback_session_id = :0:', $feedbackSession->id) as $field) {
			$fields[$field->id] = $field;
		}

		$aggregates = [];
		$totalResponses = FeedbackResponse::count('feedback_session_id = :0:', $feedbackSession->id);
		foreach ($fields as $field) {
			if ($field->type == FormFieldTypeEnum::SHORT_TEXT() || $field->type == FormFieldTypeEnum::LONG_TEXT()) {
				continue;
			}

			$fieldResults = [];

			if ($field->type == FormFieldTypeEnum::CHECKBOX_GROUP()) {
				$fieldResults['responders'] = $totalResponses;
				$fieldResults['data'] = [];
				foreach ($field->options as $ndx => $option) {
					$results = $db->query(
						"
						SELECT
							COUNT(*) as cnt
						FROM
							feedback_response_field as frf
						WHERE
							frf.feedback_session_field_id = :0: AND
							SUBSTRING_INDEX(SUBSTRING_INDEX(frf.response, ',', :1:), ',', -1) = 1
						",
						$field->id,
						$ndx + 1
					);

					$fieldResults['data'][$ndx] = intval($results[0]['cnt']);
				}
			}
			else {
				$fieldResults['responders'] = 0;
				$fieldResults['data'] = [];

				$results = $db->query(
					"
					SELECT
						frf.response as ndx,
						COUNT(*) as cnt
					FROM
						feedback_response_field as frf
					WHERE
						frf.feedback_session_field_id = :0:
					GROUP BY
						frf.response
					",
					$field->id
				);

				foreach ($results as $row) {
					$fieldResults['data'][intval($row['ndx'])] = intval($row['cnt']);
					$fieldResults['responders'] += $row['cnt'];
				}

				$allNdxs = $field->type == FormFieldTypeEnum::RATING()
					? range(1, 5)
					: array_keys($field->options);

				foreach ($allNdxs as $ndx) {
					$fieldResults['data'][$ndx] = $fieldResults['data'][$ndx] ?? 0;
				}
			}

			$aggregates[$field->id] = $fieldResults;
		}

		$allResponses = FeedbackResponse::find('feedback_session_id = :0:', $feedbackSession->id);
	
		return $this->view(compact('feedbackSession', 'fields', 'aggregates', 'totalResponses', 'allResponses'));
   }
}