<?php
// Questions of mirrored academy courses are shown in the learner's language; option positions (and so grading) are unchanged.
$this->load->library('ha_lms_i18n');
$ap_questions = get_instance()->ha_lms_i18n->questions($quiz_questions->result_array());
?>
<div class="ap-quiz__sheet">
<?php foreach($ap_questions as $question_number => $quiz_question): ?>
<?php $question_number++; ?>
<?php if($quiz_question['type'] == 'multiple_choice' || $quiz_question['type'] == 'single_choice'): ?>
	<form class="ajaxFormSubmission ap-q" id="submitForm<?php echo $question_number; ?>" action="<?php echo site_url('user/submit_quiz_answer/'.$quiz_question['quiz_id'].'/'.$quiz_question['id'].'/'.$quiz_question['type']); ?>" method="post" enctype="multipart/form-data">
		<?php $input_type = ($quiz_question['type'] == 'multiple_choice')? 'checkbox' : 'radio'; ?>
		<fieldset class="ap-q__set">
			<legend class="ap-q__legend"><span class="ap-q__no"><?php echo $question_number; ?></span><span class="ap-q__text"><?php echo remove_js(htmlspecialchars_decode_($quiz_question['title'])); ?></span></legend>
			<div class="ap-q__options">
				<?php foreach(json_decode($quiz_question['options'], true) as $key => $option): ?>
					<?php $key++; ?>
					<label class="ap-q__option" for="option_<?php echo $question_number.'_'.$key; ?>">
						<input onchange="submit_quiz_answer('submitForm<?php echo $question_number; ?>');" id="option_<?php echo $question_number.'_'.$key; ?>" type="<?php echo $input_type; ?>" value="<?php echo $key; ?>" name="answer[]">
						<span><?php echo html_escape($option); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
	</form>
<?php elseif($quiz_question['type'] == 'fill_in_the_blank'): ?>
	<form class="ajaxFormSubmission ap-q" id="submitForm<?php echo $question_number; ?>" action="<?php echo site_url('user/submit_quiz_answer/'.$quiz_question['quiz_id'].'/'.$quiz_question['id'].'/'.$quiz_question['type']); ?>" method="post" enctype="multipart/form-data">
		<fieldset class="ap-q__set">
			<?php
			$correct_answers = json_decode($quiz_question['correct_answers'], true);
			$question_title = remove_js(htmlspecialchars_decode_($quiz_question['title']));
			foreach($correct_answers as $correct_answer):
				$question_title = str_replace($correct_answer, ' _____ ', $question_title);
			endforeach;
			?>
			<legend class="ap-q__legend"><span class="ap-q__no"><?php echo $question_number; ?></span><span class="ap-q__text"><?php echo $question_title; ?></span></legend>
			<div class="input-group mb-3">
				<?php foreach($correct_answers as $key => $word): ?>
					<span class="input-group-text"><?php echo ++$key; ?></span>
					<input type="text" onblur="submit_quiz_answer('submitForm<?php echo $question_number; ?>');" class="form-control" name="answer[]">
				<?php endforeach; ?>
			</div>
		</fieldset>
	</form>
<?php endif; ?>
<?php endforeach; ?>

<form class="ajaxFormSubmission ap-q__submit" action="<?php echo site_url('user/finish_quize_submission/'.$quiz_id); ?>" method="post" enctype="multipart/form-data">
	<button id="quizSubmissionBtn" type="submit" class="btn btn-primary ap-btn ap-btn--primary"><i class="fas fa-check-circle" aria-hidden="true"></i> <span><?php echo site_phrase('submit'); ?></span></button>
</form>
</div>

<script type="text/javascript">
	function submit_quiz_answer(formId){
		$("#"+formId).submit();
	}

    $(function() {
	    // Each answer is saved the moment it is chosen. Submitting while the last
	    // save is still in flight used to close the attempt first and lose that
	    // answer, so the submit waits until every save has come back.
	    var pending = 0;
	    $('.ajaxFormSubmission').not('.ap-q__submit').ajaxForm({
	        beforeSend: function () { pending++; },
	        complete: function(xhr) {
	        	pending = Math.max(0, pending - 1);
	        	var jsonResponse = {};
	        	try { jsonResponse = JSON.parse(xhr.responseText); } catch (e) {}
	        	if(jsonResponse.status == 'time_over'){
	        		location.reload();
	        	}
	        }
	    });
	    $('.ap-q__submit').on('submit', function (e) {
	        e.preventDefault();
	        var form = this;
	        $('#quizSubmissionBtn').prop('disabled', true).addClass('is-busy');
	        (function finish() {
	            if (pending > 0) { return setTimeout(finish, 120); }
	            $.post(form.action).always(function () { location.reload(); });
	        })();
	    });
	});
</script>
