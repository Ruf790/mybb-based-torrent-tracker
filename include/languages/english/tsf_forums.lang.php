<?php
/*
************************************************
*==========[TS Special Edition v.5.6]==========*
************************************************
*              Special Thanks To               *
*        DrNet - wWw.SpecialCoders.CoM         *
*          Vinson - wWw.Decode4u.CoM           *
*    MrDecoder - wWw.Fearless-Releases.CoM     *
*           Fynnon - wWw.BvList.CoM            *
*==============================================*
*   Note: Don't Modify Or Delete This Credit   *
*     Next Target: TS Special Edition v5.7     *
*     TS SE WILL BE ALWAYS FREE SOFTWARE !     *
************************************************
*/
/* 
TS Special Edition English Language File
Translation by xam Version: 0.9

*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// TSF FORUMS (all files)
$language['tsf_forums'] = array 
(
	
	'nothreads' => 'Sorry, but there are currently no threads in this forum with the specified date and time limiting options.',
	
	'stats_posts_threads' => 'Our members have made a total of {1} posts in {2} threads.',
	'stats_numusers' => 'We currently have {1} members registered.',
	'stats_newestuser' => 'Please welcome our newest member, <b>{1}</b>',
	'stats_mostonline' => 'The most users online at one time was {1} on {2} at {3}',
	
	'standard_mod_tools' => 'Standard Tools',
	'delayed_moderation' => 'Delayed Moderation',
	'go' => 'Go',
	'inline_go' => 'Go',
	
	'redirect_postdeleted' => 'Thank you, the post has been deleted.<br />You will now be returned to the thread',
	'redirect_threaddeleted' => 'Thank you, the thread has been deleted.<br />You will now be returned to the forum',
	'redirect_nodelete' => 'The post was not deleted because you didnt check the "Delete" checkbox',
	'thread_deleted' => 'Deleted Thread Permanently',
	'thread_deleted2' => 'Thread Deleted Permanently: {1}',
	
	'edit_post2' => 'Edit This Post',
	'delete_q' => 'Delete?',
	'delete_2' => '<b>Note:</b> If this post is the first post in a thread deleting it will result in deletion of the whole thread',
	
	
	'post_deleted' => 'Deleted Post Permanently',
	'error_invalidpost' => 'Sorry, but you seem to have followed an invalid address. Please be sure the specified post exists and try again',
	
	'new_forum' => 'New Forum:',
	'move_copy_thread' => 'Move / Copy Thread',
	'method' => 'Method',
	'method_copy' => 'Copy thread to the new forum',
	'method_move' => 'Move thread',
	
	'method_move_redirect' => 'Move thread and leave redirect in existing forum for days',
    'redirect_expire_note' => '(leave blank for infinite)',
	'redirect_threadmoved' => 'The thread has been moved or copied to the selected forum successfully.<br />You will now be returned to it',
	
	'error_movetosameforum' => 'You cannot move this thread into the forum it is currently situated in. Please select a different forum.',
	
	'error_nomergeposts' => 'You need to select one or more posts to be able to merge posts together',
	'merge_posts' => 'Merge Posts',
	'new_line' => 'New Line',
    'horizontal_rule' => 'Horizontal Rule',
	'post_separator' => 'Post Separator',
	'redirect_inline_postsmerged' => 'The selected posts have been merged together.<br />You will now be returned to your previous location',
	
	'new_subject' => 'New Subject:',
	'thread_to_merge_with' => 'Thread to merge with:',
	'merge_with_note' => 'Copy the URL of the thread to be merged into this one into the textbox on the right.<br />The thread on the right will be deleted and all posts will be merged into this one',
	'merge_threads' => 'Merge Threads',
	
	'redirect_threadsmerged' => 'Thank you, the two threads have successfully been merged together.<br />You will now be taken to the new thread',
	'error_badmergeurl' => 'The URL for the thread to be merged seems to be invalid or empty. Please copy the exact URL into the textbox.<br />Please go back and try again',
	'error_mergewithself' => 'Threads cannot be merged with themselves.<br />Please go back and enter a valid URL',
	
	
	'redirect_threadsmerged' => 'Thank you, the two threads have successfully been merged together.<br />You will now be taken to the new thread',
	
	'error_nonextnewest' => 'There are no threads that are newer than the one you were previously viewing',
	'error_nonextoldest' => 'There are no threads that are older than the one you were previously viewing',
	
	'next_oldest'  => 'Next Oldest',
    'next_newest'  => 'Next Newest',
	
	'redirect_postedited' => 'Thank you, this post has been edited.<br />',
	'redirect_postedited_redirect' => 'You will now be returned to the thread.',
	
	'redirect_newthread' => 'Thank you, your thread has been posted.',
	'redirect_newthread_thread' => '<br />You will now be taken to the new thread.',
	
	'redirect_newreply' => 'Thank you, your reply has been posted.',
	'redirect_newreply_post' => '<br />You will now be taken to your post.',
	
	'redirect_return_forum' => '<br /><br />Alternatively, <a href="{1}">return to the forum</a>',
	
	'forum'			=>'Forum',
	'threads'			=>'Threads',
	'posts'			=>'Posts',
	'lastpost'			=>'Last Post',
	'stats'			=>'<b>Board Statistics</b>',
	'stats_info'		=>'Our members have made a total of <b>{1}</b> posts in <b>{2}</b> threads.<br />
							We currently have <b>{3}</b> members registered.<br />
							Please welcome our newest member, <b>{4}</b>',
	'activeusers'	=>'<b>{1}</b> users active in the past <b>{2}</b> minutes:<br />',
	'by'				=>'by',
	'invalidfid'		=>'The specified forum does not exist.',
	'invalid_tid'		=>'The specified thread does not exist.',
	'invalid_post'	=>'The specified post does not exist.',
	'noforumsyet'	=>'There is no registered forums yet!',
	'lastpost_never'=>'Never',
	'guest'			=>'Guest',
	'whosonline'		=>'<b>Who\'s Online</b>',
	'new_posts'		=>'Forum Contains New Posts',
	'no_new_posts'=>'Forum Contains No New Posts',
	'forum_locked'	=>'Forum is Closed for Posting',
	't_new_posts'	=>'New posts',
	't_no_new_posts'=>'No new posts',
	'thread_locked'=>'Thread is closed',
	'new_thread'	=>'Post Thread',
	'mark_read'		=>'Mark this forum read',
	'thread'			=>'Thread Subject',
	'author'			=>'Author',
	'replies'			=>'Replies',
	'views'			=>'Views',
	'stickythread'	=>'Important Thread!',
	'status2'			=>'Status',	//Changed name in v5.0
	'pages'			=>'Pages: ',
	'multithread'	=>'Multi-page thread',
	'new_thread'	=>'New Thread',
	'new_reply'		=>'New Reply',
	'post_edited'	=>'Thank you, this post has been edited.',
	'message'		=>'Message',
	'new_thread_in'=>'New Thread in {1}',
	'mod_options'	=>'Moderator Options:',
	'mod_options_c'=>'<b>Close Thread</b>: prevent further posting in this thread.',
	'mod_options_s'=>'<b>Stick Thread:</b> stick this thread to the top of the forum.',
	'mod_options_cc'=>'Open / Close Thread',
	'mod_options_ss'=>'Sticky / Unsticky Thread',
	'mod_options_m'=>'Move Thread',
	'mod_options_dd'=>'Delete Thread',
	'new_thread_head'=>'Post a new Thread',
	'new_reply_head'=>'Post a New Reply',
	'new_reply_head2'=>'Reply to thread: ',
	'cant_post'	=>'You may not post in this forum either because the forum is closed, or it is a category.',
	'too_short'	=>'Message or Subject is too short!',
	'thread_created'=>'The new thread has been created.',
	'no_thread'		=>'There is no thread to show.',
	'editedby'		=>'<p class=\"smalltext\">This post was last modified: {1} {2} by {3}</p>',
	'reply_post'		=>'Reply',
	'quote_post'	=>'Quote',
	'report_post'	=>'Report',
	'edit_post'		=>'Edit',
	'edit_this_post'=>'Edit Post',
	'a_post'			=>'a post',
	'delete_post'	=>'Delete',
	'pm_post'		=>'Send PM',
	'profile_post'	=>'Profile',
	'redirect_last_post'=>'Redirecting to the last post...',
	'post'				=>'Post: ',
	're'					=>'RE: ',
	'jump_text'		=>'Forum Jump: ',
	'go_button'		=>'go',
	'usergroup'		=>'Group: ',
	'jdate'			=>'Joined: ',
	'status'			=>'Status: ',
	'totalposts'		=>'Posts: ',
	'totalt'		=>'Threads: ',
	'user_offline'	=>'<font color="red">Offline</font>',
	'user_online'	=>'<font color="green">Online</font>',
	'post_done'		=>'Your new reply has been saved...',
	'thread_closed'	 =>'Sorry this thread has been closed!',
	'yes'				=>'Yes I am Sure!',
	'no'				=>'No, Please Return!',
	'cancel'			=>'Cancel',
	'mod_del_thread'=>'Delete Thread: {1}',
	'mod_del_thread_2'=>'Are you sure you wish to delete the selected thread?<br />Once a thread has been deleted it cannot be restored and any posts!',
	'mod_del_post'=>'Delete Post: {1}',
	'mod_del_post_2'=>'Are you sure you wish to delete the selected post?<br />Once a post has been deleted it cannot be restored!',
	'mod_move'		=>'Select New Forum: ',
	'warningmsg'=>'<a href="./../admin/settings.php?action=forumsettings">Your board status is currently set to closed.</a>',
	'search_results'	=>'Search Results: ',
	'search'			=>'Search',
	'title'				=>'Search Forums',
	'title1'			=>'Search by Key Word',
	'title2'			=>'Search by User Name',
	'title3'			=>'Search Options',
	'option1'			=>'Keyword(s):',
	'option2'			=>'Search Entire Post',
	'option3'			=>'Search Titles Only',
	'option4'			=>'User Name:',
	'option5'			=>'Find Posts by User',
	'option6'			=>'Find Threads Started by User',
	'option7'			=>'Exact Name',
	'option8'			=>'Search in Forum(s)',
	'button_1'		=>'Search Now',
	'button_2'		=>'Reset Fields',
	'select1'			=>'Search All Open Forums',
	'searcherror'	=>'Sorry, but no results were returned using the query information you provided.<br />Please redefine your search terms and try again.',
	'searcherror2'	=>'You did not enter any search terms.<br />At a minimum, you must enter either some search terms or a username to search by.',
	'searcherror3'	=>'One or more of your search terms were shorter than the minimum length. The minimum search term length is {1} characters.<br /><br />If you\'re trying to search for an entire phrase, enclose it within double quotes.<br />For example "The quick brown fox jumps over the lazy dog"',
	'searcherror4'	=>'An invalid search was specified.  Please go back and try again.',
	'searchresults'	=>'Thank you, your search has been submitted and you will now be taken to the results list.',
	'markforumread'=>'The selected forum has been marked as read.',
	'markforumsread'=>'All the forums have been marked as read.',
	'markallread'	=>'Mark All Forums Read',
	'country'			=>'Country: ',
	//'tooltip'			=>'<strong>Last seen:</strong> {1}<br /><strong>Downloaded:</strong> {2}<br /><strong>Uploaded:</strong> {3}<br /><strong>Ratio:</strong> {4}<br />',//Updated v4.1
	
	'tooltip'			=>'
	Last seen: {1}
	Downloaded: {2}
	Uploaded: {3}
	Ratio: {4}',//Updated v4.1
	
	
	
	'a_error1'		=>'The specified attachment does not exist.',
	'a_error2'		=>'The file upload failed. Please choose a valid file and try again.',
	'a_error3'		=>'The type of file that you attached is not allowed. Please remove the attachment or choose a different type.',
	'a_error4'		=>'The file you attached is too large. The maximum size for that type of file is {1}.',
	'a_error5'		=>'It appears this file already uploaded. Please choose a different file to attach.',
	'a_info'			=>'Attached File(s)',
	'a_size'			=>'Size: ',
	'a_count'		=>'Downloads: ',
	'attachment'	=>'Attachment:',
	'a_remove'		=>'Check this box to remove attachment from this post.',
	'deny'				=>'This user wishes to remain anonymous!',
	'thread_review'=>'Thread Review (Newest First)', // Added v3.6
	'posted_by'		=>'Posted by', // Added v3.6
	'quick_reply'	=>'Quick Reply', // Added v3.6
	'post_reply'		=>'Post Reply', // Added v3.6
	'preview_reply'	 =>'Preview Post', // Added v3.6
	'search_forum'	=>'Search this forum', // Added v3.6
	'click_hold_edit'=>'(Click and hold to edit)', // Added v3.6
	'ajax_loading' =>'Loading. <br />Please Wait..', // Added v3.6
	'saving_changes' =>'Saving changes..', // Added v3.6
	'noperm'			=>'Permission denied!', // Added v3.6
	'posted'			=>'Posted', //Added v3.7
	'announcements'=>'Announcement:', //Added v3.7
	'atitle'			=>'Forum Announcements', //Added v3.7
	'invalidaid'	=>'The specified announcement does not exist.', //Added v3.7
	'gotolastpost'	=>'Go to last post', //Added v3.7
	'deleteposts'	=>'Delete Posts', //Added v3.7
	'deletethreads'	 =>'Delete Threads', //Added v3.7
	'subs'				=>'Subscribe to this Thread', //Added v3.8
	'delsubs'			=>'Unsubscribe to the Thread', //Added v3.8
	'asubs'			=>'You are already subscribed to this thread!',  //Added v3.8
	'dsubs'			=>'A subscription for this thread has been added.', //Added v3.8
	'msubs'			=>'Dear {1},

You are subscribed to the thread {2}, there is a new posts to this thread, the last poster was {3}.

To visit this thread, please visit this page:
{4}/showthread.php?tid={5}

All the best,
{6} Team.

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
Unsubscription information:

To unsubscribe from this thread, please visit this page:
{4}/subscription.php?do=removesubscription&tid={5}', //Added v3.8
	'rsubs'			=>'A subscription for this thread has been removed!', //Added v3.8
	'isubs'				=>'You will be notified by email when someone else replies to that thread.', //Added v3.8
	'goadvanced'	=>'Go Advanced', //Added v3.9	
	'sforums'	 =>'Sub-Forums', //Added v3.9
	'tbdays'=>'Today\'s Birthdays', //Added v3.9
	'tbdayss'=>'<b>{1}</b> members are celebrating their birthday today!',  //Added v3.9
	'rate1'=>'Rate Thread',//Added v4.0
	'rate2'=>'Rate This Thread',//Added v4.0
	'rateop5'=>'Excellent',//Added v4.0
	'rateop4'=>'Good',//Added v4.0
	'rateop3'=>'Average',//Added v4.0
	'rateop2'=>'Bad',//Added v4.0
	'rateop1'=>'Terrible',//Added v4.0
	'ratenow'=>'Rate Now',//Added v4.0
	'rateresult1'=>'Your vote on this thread has been added.',//Added v4.0
	'rateresult2'=>'You have already voted on this thread.',//Added v4.0	
	'rateresult3'=>'You have selected an invalid rating for this thread.',//Added v4.0
	'rateresult4'=>'Invalid Post Hash!', //Added v4.0
	'sticky'=>'<strong>Sticky:</strong> ', //Added v4.0
	'tratingimgalt'=>'Thread Rating: {1} votes, {2} average.',//Added v4.0
	'showandclose'=>'Show Thread & Close Window',//Added v4.0
	'poll1'=>'Post a Poll',//Added v4.0
	'poll2'=>'Yes, post a poll with this thread',//Added v4.0
	'poll3'=>'Number of poll options:',//Added v4.0
	'poll4'=>'Poll Question:',//Added v4.0
	'poll5'=>'Poll Options',//Added v4.0
	'poll6'=>'Option {1}',	//Added v4.0
	'poll7'=>'Submit New Poll',//Added v4.0
	'poll8'=>'Please complete both the question field and at least 2 option fields.',//Added v4.0
	'poll9'=>'You cannot add a poll to this thread because it already has a poll attached to it.',//Added v4.0
	'poll10'=>'Thank you for posting! You will now be taken to your post. If you opted to post a poll, you will now be allowed to do so.',//Added v4.0
	'poll11'=>'You have already voted on this poll!',//Added v4.0
	'poll12'=>'This poll is closed',//Added v4.0
	'poll13'=>'You may not vote on this poll!',//Added v4.0
	'poll14'=>'View Poll Results: ',//Added v4.0
	'poll15'=>'Votes: ',//Added v4.0
	'poll16'=>'Edit Poll',//Added v4.0
	'poll17'=>'Poll',//Added v4.0
	'poll18'=>'View Poll Results',//Added v4.0
	'poll19'=>'Vote Now',//Added v4.0
	'poll20'=>'You did not select an option to vote for. Please press back to return to the poll and choose an option before voting.',//Added v4.0
	'poll21'=>'Invalid Poll!',//Added v4.0
	'poll22'=>'Closed Poll',//Added v4.0
	'poll23'=>'To close this poll, check this box.<br />Note: Closing this poll makes it impossible to vote. It however does not stop people from replying to the thread',//Added v4.0
	'modlist'=>'Moderator(s): {1}',//Added v4.1
	'hidden'=>'<i><b>Hidden</b></i>',//Added v4.1
	'fpassword'=>'Your administrator has required a password to access this forum!',//Added v4.1
	'fpassword2'=>'Please enter this password now. Note: This requires cookies!',//Added v4.1
	'fpassword3'=>'Login',//Added v4.1
	'modnotice1'			=>'Moderator Message:',//Added v4.1
	'modnotice2'			=>'Activate the checkbox to remove this Moderator Message.',//Added v4.1
	'starter'=>'Thread Starter',//Added in v5.0
	'rating'=>'Rating',//Added in v5.0
	'foptions'=>'Forum Options',//Added in v5.0
	'toptions'=>'Thread Options',//Added in v5.0
	'pthread'=>'Print This Thread',//Added in v5.0
	'ethread'=>'Email This Thread',//Added in v5.0
	'ethreadh'=>'Send Thread to a Friend',//Added in v5.0
	'fname'=>'Friend Name:',//Added in v5.0
	'femail'=>'Friend Email:',//Added in v5.0
	'tsubject'=>'Message Subject:',//Added in v5.0
	'tmsg'=>'Message:',//Added in v5.0
	'tmsgh'=>'I thought you might be interested in reading this web page: {1}

From,
{2}
	',
	'tmsgs'=>'{1},

This is a message from {2} ( {3} ) from the {4} Community Forum ( {5} ).

The message is as follows:

{6}

{4} Community Forum takes no responsibility for messages sent through its system.',//Added in v5.0
	'picons1'	=>	'Post Icons: ',//Added in v5.0
	'picons2'	=>	'You may choose an icon for your message from the following list:',//Added in v5.0
	'pcions3'	=>	'No Icon',//Added in v5.0
	'sthread' => 'Search this Thread',//Added v5.3	
	'mop1'=>'Open Threads',//Added v5.3
	'mop2'=>'Close Threads',//Added v5.3
	'mop3'=>'Sticky Threads',//Added v5.3
	'mop4'=>'Un-Sticky Threads',//Added v5.3
	'mop5'=>'Merge Threads',//Added v5.3
	'mop6'=>'Destination Thread',//Added v5.3
	'mergeposts'=>'Merge Posts',
	'mergeerror'=>'Not much would be accomplished by merging this item with itself.',//Added v5.3
	
	'moveposts'=>'Move Posts',//Added v5.7
	'moveposts2'=>'Move Posts to New Thread',//Added v5.7
	'moveposts3'=>'Move Posts to Existing Thread',//Added v5.7
	'moveposts4'=>'Use the field below to specify the id of the thread that the selected posts are to be merged into.<br />Note that all posts will be inserted into their chronological positions within this thread.',//Added v5.7
	'moveposts5'=>'Destination Forum',//Added v5.7
	'copyposts'=>'Copy Posts',//Added v5.7
	'copyposts2'=>'Copy Posts to New Thread',//Added v5.7
	'copyposts3'=>'Copy Posts to Existing Thread',//Added v5.7
	
	'top'=>'Top',//Added v5.3
	'thank'=>'The following user says thank you to {1} for this useful post:',//Added v5.6
	'thanks'=>'The following {1} users say thank you to {2} for this useful post:',//Added v5.6
	'thanked'=>'You have already thanked this post!',//Added v5.6
	/////////////////////////// From MyBB
	'emailbit_viewthread' => '... (visit the thread to read more...)',
	
'pmsubject_subscription' => "New Reply to {1}",

'pm_subscription' => "{1},

{2} has just replied to a thread which you have subscribed to. This thread is titled {3}.

Here is an excerpt of the message:
------------------------------------------
{4}
------------------------------------------

To view the thread, you can go to the following URL:
[url]{5}/{6}[/url]

There may also be other replies to this thread but you will not receive anymore notifications until you visit the thread again.

------------------------------------------
Unsubscription Information:

If you would not like to receive any more notifications of replies to this thread, visit the following URL in your browser:
[url]{5}/usercp.php?action=removesubscription&tid={7}[/url]

------------------------------------------",


);
?>
