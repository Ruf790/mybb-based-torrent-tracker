<?php
if (!defined('STAFF_PANEL_TSSEv56')) 
{
    exit ('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}




function deepdelete($id)
{
    global $torrent_dir, $db;

    if (is_valid_id($id)) 
    {
        $id = intval($id);
        $image_types = ['gif', 'jpg', 'jpeg', 'png', 'webp'];

        // ✅ Удаляем .torrent файл
        $file = TSDIR . '/' . $torrent_dir . '/' . $id . '.torrent';
        if (@file_exists($file)) 
        {
            @unlink($file); // Удаляем торрент файл
        }

        // ✅ Удаляем обложки и альтернативные версии
        foreach ($image_types as $image) 
        {
            // Основная обложка
            $image_file = TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image;
            if (@file_exists($image_file)) 
            {
                @unlink($image_file);
            }

            // Альтернативная обложка (_2)
            $alt_image_file = TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image;
            if (@file_exists($alt_image_file)) 
            {
                @unlink($alt_image_file);
            }
        }

        // ✅ Удаляем скриншоты, которые не связаны с базой данных
        $screens_dir = TSDIR . "/" . $torrent_dir . "/screens/";

        // Получаем все имена файлов в директории скринов
        $screens = scandir($screens_dir);

        // Получаем все ID торрентов из базы данных
        $torrentids = [];
        $sql = $db->sql_query('SELECT id FROM torrents');
        while ($torrent = $db->fetch_array($sql)) 
        {
            $torrentids[] = intval($torrent['id']);
        }

        // Удаляем все файлы, которые не соответствуют ID торрентов
        foreach ($screens as $screenshot) 
        {
            // Пропускаем директории
            if ($screenshot == '.' || $screenshot == '..') 
            {
                continue;
            }

            // Извлекаем ID из имени файла (например, '65464.jpg' => 65464)
            $filename = pathinfo($screenshot, PATHINFO_FILENAME);

            // Проверяем, связан ли этот скриншот с каким-либо торрентом в базе данных
            $check = $db->simple_select("screenshots", "filename", "filename = '{$screenshot}'");

			
            if (!$db->num_rows($check)) 
            {
                // Если нет записи в базе данных для этого файла, удаляем его
                $screenshotFile = $screens_dir . $screenshot;
                if (file_exists($screenshotFile)) 
                {
                    @unlink($screenshotFile); // Удаляем скриншот
                }
            }
        }

        // ✅ Удаляем IMDB обложки и фотографии
        $query = $db->simple_select("torrents", "t_link", "id = '{$id}'");
        if ($db->num_rows($query)) 
        {
            $Result = $db->fetch_array($query);
            $t_link = $Result["t_link"];
            $regex = "#https://www.imdb.com/title/(.*)/#U";
            preg_match($regex, $t_link, $_id_);
            if (isset($_id_[1]) && $_id_[1]) 
            {
                $_id_ = $_id_[1];
                foreach ($image_types as $image) 
                {
                    // Удаляем изображения IMDb
                    if (@file_exists(TSDIR . "/" . $torrent_dir . "/imdb/" . $_id_ . "." . $image)) 
                    {
                        @unlink(TSDIR . "/" . $torrent_dir . "/imdb/" . $_id_ . "." . $image);
                    }
                }

                // Удаляем фото IMDb
                for ($i = 0; $i <= 10; $i++) 
                {
                    foreach ($image_types as $image) 
                    {
                        if (@file_exists(TSDIR . "/" . $torrent_dir . "/imdb/" . $_id_ . "_photo" . $i . "." . $image)) 
                        {
                            @unlink(TSDIR . "/" . $torrent_dir . "/imdb/" . $_id_ . "_photo" . $i . "." . $image);
                        }
                    }
                }
            }
        }

        // ✅ Удаляем связанные записи из БД
        $db->delete_query("peers", "torrent='$id'");
        $db->delete_query("comments", "torrent='$id'");
        $db->delete_query("bookmarks", "torrentid='$id'");
        $db->delete_query("snatched", "torrentid='$id'");
        $db->delete_query("torrents", "id='$id'");
        $db->delete_query("ratings", "type='1' AND rating_id='$id'");
        $db->delete_query("reports", "type='torrent' AND votedfor='$id'");
        $db->delete_query("ts_nfo", "id='$id'");
    }
}






















$torrentids = [];
$sql = $db->sql_query('SELECT id FROM torrents');
while ($torrent = $db->fetch_array($sql)) 
{
    $torrentids[] = intval($torrent['id']);
}

$files = [];
if ($handle = opendir(TSDIR . '/' . $torrent_dir)) 
{
    while (false !== ($file = readdir($handle))) 
	{
        if ($file != '.' && $file != '..' && substr($file, -8) === '.torrent') 
		{
            $file = str_replace('.torrent', '', $file);
            $file = intval($file);
            $files[] = $file;
        }
    }
    closedir($handle);
}

$delete = [];
foreach ($files as $file) 
{
    if (!in_array($file, $torrentids, true)) 
	{
        $delete[] = $file;
    }
}

$deleted_ids = [];

if (isset($_GET['sure']) && $_GET['sure'] === 'yes') 
{
    foreach ($delete as $file) 
	{
        deepdelete($file);
        $deleted_ids[] = $file;
    }
    // Очищаем список, чтобы после удаления не показывать
    $delete = [];
}



stdhead('Delete Undeleted Torrent Files');

echo '
<div class="container-md">
  <div class="card border-0 mb-4">
    <div class="card-header rounded-bottom text-19 fw-bold">
      Delete Undeleted Torrent Files
    </div>
  </div>
</div>';

echo '<div class="container mt-3">
  <div class="card">
    <div class="card-body">';

// ✅ Сообщение об успешном удалении
if (!empty($deleted_ids)) 
{
    echo '<div class="alert alert-success" role="alert">
        ✅ <b>Successfully deleted ' . count($deleted_ids) . ' files.</b>
    </div>';

    // Лог удалённых ID
    echo '<p>Deleted torrent IDs:</p>';
    echo '<div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Torrent ID</th>
                </tr>
            </thead>
            <tbody>';
    $num = 1;
    foreach ($deleted_ids as $id) 
	{
        echo '<tr>
            <th scope="row">' . $num . '</th>
            <td>' . htmlspecialchars($id) . '</td>
        </tr>';
        $num++;
    }
    echo '</tbody>
        </table>
    </div>';
}

if (!empty($delete)) 
{
    echo '<p>Total <b>' . count($delete) . '</b> orphaned .torrent files found in <code>' . $torrent_dir . '</code> folder.</p>';
    echo '<div class="table-responsive">
      <table class="table table-bordered table-striped table-hover">
        <thead class="table-dark">
          <tr>
            <th scope="col">#</th>
            <th scope="col">Torrent ID</th>
          </tr>
        </thead>
        <tbody>';
    $num = 1;
    foreach ($delete as $id) 
	{
        echo '<tr>
          <th scope="row">' . $num . '</th>
          <td>' . htmlspecialchars($id) . '</td>
        </tr>';
        $num++;
    }
    echo '</tbody>
      </table>
    </div>';
    echo '<a href="https://ruff-tracker.eu/admin/index.php?act=delundeletedtorrents&sure=yes" class="btn btn-danger mt-3" onclick="return confirm(\'Are you sure you want to permanently delete these files?\')">Delete All</a>';
} 
elseif (empty($deleted_ids)) 
{
    echo '<p class="text-success">There are no undeleted torrents found. Everything is clean!</p>';
}

echo '</div>
  </div>
</div>';

// ✅ Toast уведомление
if (!empty($deleted_ids)) {
    echo '
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="deletedToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ✅ Successfully deleted ' . count($deleted_ids) . ' files!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script>
        var toastEl = document.getElementById("deletedToast");
        var toast = new bootstrap.Toast(toastEl);
        toast.show();
    </script>';
}

stdfoot();
?>
