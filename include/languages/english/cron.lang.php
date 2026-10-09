<?php
if (!defined('IN_TRACKER')) die('Hacking attempt!');

// Cron PMs (sent by the system, BBCode). English.
// Placeholder order is fixed by the sprintf() calls in the cron files —
// keep {N} meanings identical in every language. Key sets must match russian.

$language['cron'] = array (

	// ── Referral system ──────────────────────────────────────────
	'r_subject'        => 'Gift from the Referral System!',
	'r_message'        => 'Hi,

Thank you for using our Referral System.

You have earned {1} credit(s).

Kind regards.',
	// {1} = credits

	// ── Automatic invites ────────────────────────────────────────
	'invite_subject'   => 'Automatic Invite!',
	'invite_message'   => 'Congratulations, you have received {1} invite(s).

If you would like to invite your friends, please click [url=invite.php?id={2}]here[/url].',
	// {1} = invites, {2} = user id

	// ── Donor / VIP expiry ───────────────────────────────────────
	'donor_subject'    => 'Donor status removed by system',
	'donor_message'    => 'Hi,

Your Donor status has expired and has been automatically removed by the system, along with your VIP status.

We would like to thank you once again for your support.

If you wish to renew your donation, you can do so by clicking [url=donate.php]here[/url].

Kind regards.',

	'vip_subject'      => 'VIP status removed by system',
	'vip_message'      => 'Hi,

Your VIP status has expired and has been automatically removed by the system.

You can become a VIP again by donating or by exchanging some Bonus Points.

Kind regards.',

	// ── Class promotion / demotion ───────────────────────────────
	'promote_subject'  => 'Account Promoted!',
	'promote_message'  => 'Congratulations, you have been automatically promoted to [b]Power User[/b]. :)',
	'demote_subject'   => 'Account Demoted!',
	'demote_message'   => 'You have been automatically demoted from [b]Power User[/b] to [b]User[/b] because your share ratio has dropped below {1}.',
	// {1} = ratio threshold

	// ── Low-ratio (leech) warning ────────────────────────────────
	'lwarning_subject' => 'You have been Leech-Warned!',
	'lwarning_message' => 'You have been warned because of a low ratio. You need to reach a ratio of {1} within the next {2} week(s), or your account will be banned.',
	// {1} = required ratio, {2} = weeks

	// ── Hit & Run (weekly_cleanups.php → hr_send_pm()) ───────────
	// {1} username  {2} torrent link  {3} hours seeded  {4} required hours
	// {5} download link  {6} required hours  {7} warning limit  {8} hours left
	'hr_warn_subject'  => 'Hit and Run Warning!',
	'hr_warn_message'  => '[b]{1}[/b],

You have been warned for Hit & Run on the following torrent:
[b]{2}[/b]

You have seeded this torrent [b]{3}[/b] hour(s) but it must be seeded [b]{4}[/b] hour(s).

Please re-start seeding this torrent or you will be warned again soon.
If you don\'t have this torrent on your computer, please click on the following link to download & seed it.
[b]{5}[/b]

All torrents must be seeded at least [b]{6}[/b] hour(s) after finishing, otherwise users get [b]+1[/b] warning per torrent.
Please note: once your total warnings reach the limit of [b]{7}[/b], your account will be banned.

Thank you for your understanding and support.
Have a great day.',

	'hr_final_subject' => 'FINAL Hit and Run Warning!',
	'hr_final_message' => '[b]{1}[/b],
This is your FINAL WARNING for Hit & Run on:
[b]{2}[/b]
You have seeded [b]{3}[/b] hour(s) but must seed [b]{4}[/b] hour(s).
You still need [b]{8}[/b] more hour(s).
[b]{5}[/b]
Your account will be BANNED if you do not resume seeding immediately.',

);
