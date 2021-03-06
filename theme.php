<?php
  require 'info.php';
  
  function awsumchan_build($action, $settings, $board) {
    // Possible values for $action:
    //  - all (rebuild everything, initialization)
    //  - news (news has been updated)
    //  - boards (board list changed)
    //  - post (a post has been made)
    //  - post-thread (a thread has been made)
    //  - post-delete (a post has been deleted)
    
    $b = new AwsumChan();
    $b->build($action, $settings);
  }
  
  // Wrap functions in a class so they don't interfere with normal Tinyboard operations
  class AwsumChan {
    private $news;
    private $excluded_boards;
    private $nsfw_boards;

    public function build($action, $settings) {
      global $config, $_theme;

      $frames_enabled = ($settings['file_frames'] !== '' && $settings['file_sidebar'] !== '');
      
      if ($action === 'all') {
        copy('templates/themes/awsumchan/' . $settings['basecss'], $config['dir']['home'] . $settings['file_css']);
        copy('templates/themes/awsumchan/awsumchan.js', $config['dir']['home'] . $settings['file_js']);
        
        if ($settings['file_logo'] !== '')
          copy('templates/themes/awsumchan/logo.png', $config['dir']['home'] . $settings['file_logo']);

        if ($settings['file_favicon'] !== '')
          copy('templates/themes/awsumchan/icon.ico', $config['dir']['home'] . $settings['file_favicon']);
      }

      if ($frames_enabled) {
        switch ($action) {
          case 'all':
            file_write($config['dir']['home'] . $settings['file_frames'], $this->frames($settings));
          case 'boards':
            file_write($config['dir']['home'] . $settings['file_sidebar'], $this->sidebar($settings));
        }
      }

      $query = query('SELECT * FROM ``news`` ORDER BY `time` DESC') or error(db_error());
      $this->news = $query->fetchAll(PDO::FETCH_ASSOC);
      
      $this->excluded_boards = explode(' ', $settings['excluded_boards']);
      $this->nsfw_boards = explode(' ', $settings['nsfw_boards']);
      
      if ($action === 'all' || $action === 'news' || $action === 'boards' || $action === 'post' || $action === 'post-thread' || $action === 'post-delete')
        file_write($config['dir']['home'] . $settings['file_index'], $this->homepage($settings));
      
      if ($action === 'all' || $action === 'news')
        file_write($config['dir']['home'] . $settings['file_news'], $this->newspage($settings));

      if ($action === 'all')
        file_write($config['dir']['home'] . $settings['file_faq'], $this->faq($settings));
    }
    
    // Build news page
    public function homepage($settings) {
      global $config, $board;

      $categories = $config['categories'];

      foreach ($categories as &$_boards) {
        foreach ($_boards as &$_board) {
          $title = boardTitle($_board);

          if (!$title)
            $title = $_board;
          
          $_board = ['title' => $title, 'uri' => sprintf($config['board_path'], $_board)];
        }
      }
      
      $recent_images = [];
      $recent_posts = [];
      $stats = [];
      
      $boards = listBoards();
      
      $query = '';
      foreach ($boards as &$_board) {
        if (in_array($_board['uri'], $this->excluded_boards))
          continue;

        $query .= sprintf("SELECT *, '%s' AS `board`, %d AS `nsfw` FROM ``posts_%s`` WHERE `files` IS NOT NULL UNION ALL ",
                          $_board['uri'],
                          (int)in_array($_board['uri'], $this->nsfw_boards),
                          $_board['uri']);
      }
      $query = preg_replace('/UNION ALL $/', 'ORDER BY `time` DESC LIMIT ' . (int)$settings['limit_images'], $query);
      
      if ($query == '') {
        error(_("Can't build the AwsumChan theme, because there are no boards to be fetched."));
      }

      $query = query($query) or error(db_error());
      
      while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
        openBoard($post['board']);

        if (isset($post['files']))
          $files = json_decode($post['files']);

        // TODO: Find a fallback if there's a deleted file in the query
        if ($files[0]->file == 'deleted' || $files[0]->thumb == 'file')
          continue;
        
        // board settings won't be available in the template file, so generate links now
        $post['link'] = $config['root'] . $board['dir'] . $config['dir']['res']
          . link_for($post) . '#' . $post['id'];

        if ($files) {
          if ($files[0]->thumb == 'spoiler') {
            $tn_size = @getimagesize($config['spoiler_image']);
            $post['src'] = $config['spoiler_image'];
            $post['thumbwidth'] = $tn_size[0];
            $post['thumbheight'] = $tn_size[1];
          } else {
            $post['src'] = $config['uri_thumb'] . $files[0]->thumb;
            $post['thumbwidth'] = $files[0]->thumbwidth;
            $post['thumbheight'] = $files[0]->thumbheight;
          }
        }
        
        $recent_images[] = $post;
      }
      
      
      $query = '';
      foreach ($boards as &$_board) {
        if (in_array($_board['uri'], $this->excluded_boards))
          continue;

        $query .= sprintf("SELECT *, '%s' AS `board`, %d AS `nsfw` FROM ``posts_%s`` UNION ALL ",
                          $_board['uri'],
                          (int)in_array($_board['uri'], $this->nsfw_boards),
                          $_board['uri']);
      }
      $query = preg_replace('/UNION ALL $/', 'ORDER BY `time` DESC LIMIT ' . (int)$settings['limit_posts'], $query);
      $query = query($query) or error(db_error());
      
      while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
        openBoard($post['board']);
        
        $post['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post) . '#' . $post['id'];
        if ($post['body'] != "")
          $post['snippet'] = pm_snippet($post['body'], 30);
        else
          $post['snippet'] = "<em>" . _("(no comment)") . "</em>";
        $post['board_name'] = $board['name'];
        
        $recent_posts[] = $post;
      }
      
      // Total posts
      $query = 'SELECT SUM(`top`) FROM (';
      foreach ($boards as &$_board)
        $query .= sprintf("SELECT MAX(`id`) AS `top` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
      $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
      $query = query($query) or error(db_error());
      $stats['total_posts'] = number_format($query->fetchColumn());
      
      // Unique IPs
      $query = 'SELECT COUNT(DISTINCT(`ip`)) FROM (';
      foreach ($boards as &$_board)
        $query .= sprintf("SELECT `ip` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
      $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
      $query = query($query) or error(db_error());
      $stats['unique_posters'] = number_format($query->fetchColumn());
      
      // Active content
      $query = 'SELECT DISTINCT(`files`) FROM (';
      foreach ($boards as &$_board)
        $query .= sprintf("SELECT `files` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
      $query = preg_replace('/UNION ALL $/', ' WHERE `num_files` > 0) AS `posts_all`', $query);
      $query = query($query) or error(db_error());
      $files = $query->fetchAll();
      $stats['active_content'] = 0;
      foreach ($files as &$file) {
        preg_match_all('/"size":([0-9]*)/', $file[0], $matches);
        $stats['active_content'] += array_sum($matches[1]);
      }
      
      return Element('themes/awsumchan/index.html', [
        'settings' => $settings,
        'config' => $config,
        'mod' => false,
        'boardlist' => createBoardlist(),
        'news' => $this->news,
        'categories' => $categories,
        'recent_images' => $recent_images,
        'recent_posts' => $recent_posts,
        'stats' => $stats
      ]);
    }

    public function newspage($settings)
    {
      global $config;

      return Element('themes/awsumchan/news.html', [
        'settings' => $settings,
        'config' => $config,
        'mod' => false,
        'boardlist' => createBoardlist(),
        'news' => $this->news
      ]);
    }

    public function faq($settings)
    {
      global $config;

      return Element('themes/awsumchan/faq.html', [
        'settings' => $settings,
        'config' => $config,
        'mod' => false,
        'boardlist' => createBoardlist()
      ]);
    }

    public function frames($settings)
    {
      global $config;

      return Element('themes/awsumchan/frames.html', [
        'config' => $config,
        'settings' => $settings
      ]);
    }

    public function sidebar($settings)
    {
      global $config;

			$categories = $config['categories'];

			foreach ($categories as &$_boards) {
			  foreach ($_boards as &$_board) {
				  $title = boardTitle($_board);
	  
				  if (!$title)
				    $title = $_board;
				
				  $_board = [
            'title' => $title,
            'uri' => sprintf($config['board_path'], $_board)
          ];
			  }
			}
			
			return Element('themes/awsumchan/sidebar.html', [
				'settings' => $settings,
				'config' => $config,
				'categories' => $categories
      ]);
    }
  };
  
?>
