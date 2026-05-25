<?php

declare(strict_types=1);

function ts_seo(int|string $id, string $text, string $type = 'u', string $ext = '.ts'): string
{
    global $BASEURL, $seourls;
    
    if ($seourls === 'yes') {
        $cleanText = strtolower(preg_replace(['/[^\w\s]/', '/\s+/'], '_', $text) ?? '');
        $cleanText = preg_replace('/_+/', '_', $cleanText) ?? '';
        $cleanText = trim($cleanText, '_');
        
        return match($type) {
            'a' => sprintf('%s/%s-a-%s%s', $BASEURL, $cleanText, htmlspecialchars((string)$id), $ext),
            'u' => sprintf('%s/%s-u%d%s', $BASEURL, $cleanText, (int)$id, $ext),
            default => sprintf('%s/%s-%s-%d%s', $BASEURL, $cleanText, $type, (int)$id, $ext)
        };
    }

    return match($type) {
        'a' => $BASEURL . '/announce.php?passkey=' . urlencode((string)$id),
        'b' => $BASEURL . '/browse.php?cat=' . (int)$id,
        'c' => $BASEURL . '/browse.php?browse_categories&category=' . (int)$id,
        'd' => $BASEURL . '/download.php?id=' . (int)$id,
        's' => $BASEURL . '/details.php?id=' . (int)$id,
        'u' => $BASEURL . '/userdetails.php?id=' . (int)$id,
        default => $BASEURL . '/userdetails.php?id=' . (int)$id
    };
}

  if ((!defined ('APP_INITIALIZED') AND !defined ('IN_CRON')))
  {
    exit('<div style="color:darkred;font-family:verdana;font-size:12px"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
  }

?>
