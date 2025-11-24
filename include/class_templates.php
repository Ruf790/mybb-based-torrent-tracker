<?php

declare(strict_types=1);

class templates
{
    
    public int $total = 0;

    public array $cache = [];

    public array $uncached_templates = [];

    
    public function cache(string $templates): void
    {
        global $db, $theme;
    
        $names = array_map('trim', explode(",", $templates));
        $names = array_filter($names); 
    
        if (empty($names)) 
        {
           return;  
        }
    
        $theme['templateset'] = 2;
    
        $placeholders = implode(',', array_fill(0, count($names), '?'));
    
        $query = $db->sql_query_prepared("
          SELECT title, template
          FROM templates
          WHERE title IN ($placeholders) 
          AND sid IN (-2, -1, ?)
          ORDER BY sid ASC", array_merge($names, [(int)$theme['templateset']]));

        if($query && isset($query->result)) {
            while ($template = $db->fetch_array($query->result)) 
            {
                $this->cache[$template['title']] = $template['template'];
            }
        }
    }


    public function get(string $title, bool $eslashes = true, bool $htmlcomments = true): string
    {
        global $db, $theme;

        if(!isset($this->cache[$title]))
        {
            $theme['templateset'] = "2";
            
            // Only load master and global templates if template is needed in Admin CP
            if(empty($theme['templateset']))
            {
                $query = $db->simple_select("templates", "template", "title='".$db->escape_string($title)."' AND sid IN ('-2','-1')", array('order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1));
            }
            else
            {
                $query = $db->simple_select("templates", "template", "title='".$db->escape_string($title)."' AND sid IN ('-2','-1','".(int)$theme['templateset']."')", array('order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1));
            }

            $gettemplate = $db->fetch_array($query);
            
            $this->uncached_templates[$title] = $title;

            if(empty($gettemplate))
            {
                $gettemplate = array('template' => '');
            }

            $this->cache[$title] = $gettemplate['template'] ?? '';
        }
        
        $template = $this->cache[$title] ?? '';

        if($htmlcomments)
        {
            $tplhtmlcomments = "1";
            
            if($tplhtmlcomments == 1)
            {
                $template = "<!-- start: ".htmlspecialchars_uni($title)." -->\n{$template}\n<!-- end: ".htmlspecialchars_uni($title)." -->";
            }
            else
            {
                $template = "\n{$template}\n";
            }
        }

        if($eslashes)
        {
            $template = str_replace("\\'", "'", addslashes($template));
        }
        
        return $template;
    }

   
    public function render(string $template, bool $eslashes = true, bool $htmlcomments = true): string
    {
        return 'return "'.$this->get($template, $eslashes, $htmlcomments).'";';
    }
}