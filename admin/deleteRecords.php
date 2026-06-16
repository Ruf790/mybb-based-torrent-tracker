<?php
$rootpath = './../';
$thispath = './';
define ('IN_ADMIN_PANEL', true);
define ('STAFF_PANEL', true);
define ('SKIP_CRON_JOBS', true);
define ('SKIP_LOCATION_SAVE', true);
define("IN_MYBB", 1);
define("IN_ADMINCP", 1);

require_once $rootpath . 'global.php';
require_once $rootpath . '/include/datahandler.php';
require_once $thispath . 'include/adminfunctions.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if it's a POST request and empid is set
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['empid'])) 
{
    try {
        $empid = (int)$_POST['empid'];
        
        if($empid <= 0) {
            throw new Exception('Invalid employee ID');
        }
        
        $user = get_user($empid);

        // Does the user not exist?
        if(!$user)
        {
            throw new Exception('User does not exist');
        }

        // Set up user handler.
        require_once INC_PATH."/datahandlers/user.php";
        $userhandler = new UserDataHandler('delete');

        // Delete the user
        $delete_result = $userhandler->delete_user([$user['id']]);
        
        if(!$delete_result)
        {
            throw new Exception('This user cannot be deleted');
        }

        // Log the deletion
		write_log('Following user has been deleted by ' . $CURUSER['username'] . ' (latest_users tool - Staff Panel): Userid: ' . $empid);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Record deleted successfully'
        ]);
        
    } catch (Exception $e) {
        // Return error response
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} 
else 
{
    // Invalid request
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method or missing employee ID'
    ]);
}

exit(); // Always exit after sending response
?>