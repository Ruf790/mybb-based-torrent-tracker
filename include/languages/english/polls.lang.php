<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// polls.php — create / edit / vote / results
$language['polls'] = array
(
    // ── Navigation / page titles ──────────────────────────────────────────
    'nav_postpoll'          => 'Post Poll',
    'nav_editpoll'          => 'Edit Poll',
    'nav_pollresults'       => 'Poll Results',
    'title_newpoll'         => 'Post New Poll',
    'title_editpoll'        => 'Edit Poll',
    'title_results'         => 'Poll Results',

    // ── Section headers ───────────────────────────────────────────────────
    'sec_create_poll'       => 'Create Poll',
    'sec_edit_poll'         => 'Edit Poll',

    // ── Field labels ──────────────────────────────────────────────────────
    'lbl_question'          => 'Question',
    'lbl_answer_options'    => 'Answer Options',
    'lbl_options'           => 'Options',
    'lbl_multiple'          => 'Allow multiple answers',
    'lbl_public'            => 'Show who voted',
    'lbl_closed'            => 'Close poll',
    'lbl_timeout'           => 'Duration (days)',
    'lbl_preview'           => 'Preview',

    // ── Placeholders / hints ──────────────────────────────────────────────
    'ph_question'           => 'For example: Which tracker is the best?',
    'ph_maxoptions'         => 'Max selections',
    'hint_maxoptions'       => 'Maximum number of options per user (0 = no limit)',
    'hint_timeout'          => 'Number of days people can vote (0 = the poll never closes)',

    // ── Buttons ───────────────────────────────────────────────────────────
    'btn_add_option'        => 'Add Option',
    'btn_create_poll'       => 'Create Poll',
    'btn_update_poll'       => 'Update Poll',

    // ── Misc text ─────────────────────────────────────────────────────────
    'txt_no_options'        => 'No options yet',

    // ── Results ───────────────────────────────────────────────────────────
    'lbl_total_votes'       => 'Total: {1} votes — {2}',
    'lbl_guest_one'         => '1 guest',
    'lbl_guest_many'        => '{1} guests',
    'tip_your_vote'         => 'Your vote',

    // ── Flash messages ────────────────────────────────────────────────────
    'flash_poll_created'    => 'Poll created successfully',
    'flash_poll_updated'    => 'Poll updated successfully',
    'flash_vote_thanks'     => 'Thank you for voting. You will now be returned to the thread.',
    'flash_unvoted'         => 'Your vote(s) in this poll have been removed. You will now be returned to the thread.',

    // ── Errors ────────────────────────────────────────────────────────────
    'err_invalid_thread'    => 'The specified thread is invalid or does not exist.',
    'err_invalid_forum'     => 'The specified forum is invalid or does not exist.',
    'err_forum_closed'      => 'This forum is closed or invalid.',
    'err_poll_exists'       => 'This thread already has a poll.',
    'err_poll_time_limit'   => 'Sorry, you cannot add a poll to this thread. Polls can only be added within {1} hours of posting the thread.',
    'err_invalid_poll'      => 'The specified poll is invalid or does not exist.',
    'err_question_required' => 'Please enter a question for the poll.',
    'err_option_sequence'   => 'Poll options must not contain the sequence ||~|~||.',
    'err_option_too_long'   => 'One of the options is too long (max {1} characters).',
    'err_min_options'       => 'At least {1} options are required.',
    'err_max_options_count' => 'Too many options (max {1}).',
    'err_no_option'         => 'The specified poll option is invalid or does not exist.',
    'err_already_voted'     => 'You have already voted in this poll.',
    'err_not_voted'         => 'You have not voted in this poll.',
    'err_poll_closed'       => 'You cannot vote in a poll that has been closed.',
    'err_max_votes'         => 'You have voted for too many options. You may vote for at most {1} options.',

    // ── JS strings (polls.js) ─────────────────────────────────────────────
    'js_error'              => 'Error',
    'js_done'               => 'Done!',
    'js_saved'              => 'Saved successfully',
    'js_something_wrong'    => 'Something went wrong',
    'js_server_unavailable' => 'Server unavailable',
    'js_question_required'  => 'Please enter a question',
    'js_min_options'        => 'Minimum {1} options required',
    'js_at_least_options'   => 'At least {1} options are required',
    'js_no_options'         => 'No options yet',
    'js_remove_option'      => 'Remove option',
    'js_drag_hint'          => 'Drag to reorder',
    'js_option_n'           => 'Option {1}',
    'js_votes'              => 'Votes',
);
?>
