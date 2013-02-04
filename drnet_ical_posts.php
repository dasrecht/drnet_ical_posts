<?php
/*
Plugin Name: dasrecht.net - iCal Posts
Plugin URI: http://www.dasrecht.net
Description: Creates an iCal feed which can be added to calendar applications such as Google Calendar, Apple iCal and Microsoft Outlook to create a visual representation of your blog posting.
Version: 1.0
Author: Bastian Widmer
Author URI: http://blog.dasrecht.net
*/

function drnet_ical_feed()
{
    global $wpdb;

    if (isset($_GET['debug']))
        define('DEBUG', true);

    if ($_GET['category'])
    {
        $categories = get_categories();
        foreach ($categories as $category)
        {
            if ($_GET['category'] == $category->category_nicename)
            {
                $category_id = $category->cat_ID;
                break;
            }
        }
        if (!$category_id)
            $category_id = 0;
    }

    if (is_numeric(get_option('drnet_icalposts_intshowposts')))
        $limit = 'LIMIT ' . get_option('drnet_icalposts_intshowposts');

    // get posts

    if(get_option('drnet_icalposts_blnfutureposts') == 'on' AND get_option('drnet_icalposts_blnpublishedposts') == 'on')
        {
            $posts = $wpdb->get_results("SELECT ID, post_content, UNIX_TIMESTAMP(post_date) AS post_date, post_title FROM $wpdb->posts WHERE post_status = 'future' OR post_status='publish' AND post_type = 'post' ORDER BY post_date DESC $limit;");
            $text = "Future and already Published Posts";
        }

    else if(get_option('drnet_icalposts_blnfutureposts')  == 'on')
        {
            $posts = $wpdb->get_results("SELECT ID, post_content, UNIX_TIMESTAMP(post_date) AS post_date, post_title FROM $wpdb->posts WHERE post_status = 'future' AND post_type = 'post' ORDER BY post_date DESC $limit;");
            $text = "Only Future Posts";

        }

    else if(get_option('drnet_icalposts_blnpublishedposts')  == 'on')
        {
            $posts = $wpdb->get_results("SELECT ID, post_content, UNIX_TIMESTAMP(post_date) AS post_date, post_title FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'post' ORDER BY post_date DESC $limit;");
            $text = "Only already Published Posts";

        }

    $events = '';
    $space = '      ';
    $calidentifier = get_option('drnet_icalposts_apikey');
    foreach ($posts as $post)
    {
        $start_time = date('Ymd\THis', $post->post_date);
        $start_time = $start_time."Z";
        $end_time = date('Ymd\THis', $post->post_date + (1));
        $end_time = $end_time."Z";
        $summary = '[blog] '.$post->post_title;
        $uid = $post->ID;
        $permalink = get_permalink($post->ID);
        if (isset($_GET['content']))
        {
            $content = str_replace(',', '\,', str_replace('\\', '\\\\', str_replace("\n", "\n" . $space, strip_tags($post->post_content))));
            $content = $permalink . "\n" . $space . "\n" . $space . $content;
        }
        else
            $content = $permalink;

        $events .= <<<EVENT
BEGIN:VEVENT
DTSTART:$start_time
DTEND:$end_time
DTSTAMP:$start_time
UID:$uid
SUMMARY:$summary
DESCRIPTION:$content
URL:$content
STATUS:CONFIRMED
END:VEVENT

EVENT;
    }

    $blog_name = get_bloginfo('name');
    $blog_url = get_bloginfo('home');

    if (!defined('DEBUG'))
    {
        header('Content-type: text/calendar');
        //header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename="blog_posts.ics"');
    }
    else
    {
        header('Content-type: text/plain');
    }

    $apostrophe = (isset($_GET['content']) ? "'" : '&#8217;');

    $content = <<<CONTENT
BEGIN:VCALENDAR
PRODID:-{$calidentifier}-//$blog_name//NONSGML v1.0//EN
VERSION:2.0
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME;VALUE=TEXT:{$blog_name} posts
X-WR-TIMEZONE:Europe/Zurich
X-ORIGINAL-URL:{$blog_url}
X-WR-CALDESC:{$text} from {$blog_name}
{$events}END:VCALENDAR
CONTENT;

    echo $content;

    exit;
}

if (isset($_GET[get_option('drnet_icalposts_apikey')]))
{
    add_action('init', 'drnet_ical_feed');
}


function icalposts_admin_page() {

    if (isset($_POST['drnet_icalposts_clear']))  { drnet_icalposts_reset(); }
    if (isset($_POST['topsy_clear']) && $_POST['topsy_clear'])  { drnet_icalposts_reset(); }
    ?>
    <div class="wrap">
        <h2>Configure your iCal Feed</h2>

        <b>Instructions for use:</b><br />
        1. Put an API Key in the Field (will be used for "authenticating" yourself. I'ts a random value<br />
        2. Mark wich Posts should be displayed in your iCal Feed<br />
        4. Add the Feed URL to you iCAL or GOOGLE Calendar<br />
        5. Enjoy!<br />

        <form method="post" action="options.php">
            <?php wp_nonce_field('update-options'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Key</th>
                    <td><input type="text" name="drnet_icalposts_apikey" value="<?php if (get_option('drnet_icalposts_apikey')) { echo get_option('drnet_icalposts_apikey'); } else { echo str_replace('==', '', base64_encode(rand())); } ?>"><span class="description">This is your API Key to authenticate against your Blog. If you clear the field a new Key will be generated randomly.</span></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Show Future Posts</th>
                    <td><input type="checkbox" name="drnet_icalposts_blnfutureposts" <?php print (get_option('drnet_icalposts_blnfutureposts') == 'on') ? ' checked="checked"' : ''; ?>" /><span class="description">Would you like to see your future Posts in your Calendar?</span></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Show Published Posts</th>
                    <td><input type="checkbox" name="drnet_icalposts_blnpublishedposts" <?php print (get_option('drnet_icalposts_blnpublishedposts') == 'on') ? ' checked="checked"' : ''; ?>" /><span class="description">Would you like to see the Published Posts in your Calendar</span></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Limit Feed</th>
                    <td><input type="text" name="drnet_icalposts_intshowposts" value="<?php echo get_option('drnet_icalposts_intshowposts'); ?>" /><span class="description">How much Posts should be displayed in your Calendar?</span></td>
                </tr>

                <tr valign="top">
                    <th scope="row">et voilà your URL</th>
                    <td><code><a href="<?php echo get_option('siteurl').'/?'.get_option('drnet_icalposts_apikey'); ?>"><?php echo get_option('siteurl').'/?'.get_option('drnet_icalposts_apikey'); ?></a></code></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Add to your Calendar</th>
                    <td><a href="http://www.google.com/calendar/render?cid=<?php echo get_option('siteurl').'/?'.get_option('drnet_icalposts_apikey'); ?>" target="_blank"><img src="http://www.google.com/calendar/images/ext/gc_button6.gif" border=0></a></td>
                </tr>


            </table>
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="page_options" value="drnet_icalposts_apikey,drnet_icalposts_blnfutureposts,drnet_icalposts_blnpublishedposts,drnet_icalposts_intshowposts" />
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                <input name="drnet_icalposts_clear" id="reset" value="<?php _e('Reset Options', 'drnet_icalposts_clear') ?>" type="submit"/>
            </p>
        </form>

        <form method="post" action="options.php">
            <?php wp_nonce_field('update-options'); ?>
            <table class="form-table">
            </table>
            <input type="hidden" name="action" value="delete" />
            <input type="hidden" name="page_options" value="drnet_icalposts_apikey,drnet_icalposts_blnfutureposts,drnet_icalposts_blnpublishedposts,drnet_icalposts_intshowposts" />
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Uninstall Extension') ?>" />
            </p>
        </form>
    </div>
<?php
}


function drnet_icalposts_menu() {
  add_options_page('iCalPosts Options', 'Post Calendar', 1, 'icalposts', 'icalposts_admin_page');
      if( !get_option('drnet_icalposts_enabled') ) { drnet_icalposts_reset(); }

}


function drnet_icalposts_reset() {
    add_option('drnet_icalposts_enabled', 'true');
    add_option('drnet_icalposts_apikey', str_replace('==', '', base64_encode(rand())));
    add_option('drnet_icalposts_blnfutureposts', 'off');
    add_option('drnet_icalposts_blnpublishedposts', 'off');
    add_option('drnet_icalposts_intshowposts', '20');
}

function drnet_icalposts_uninstall() {
    delete_option('drnet_icalposts_enabled');
    delete_option('drnet_icalposts_apikey');
    delete_option('drnet_icalposts_blnfutureposts');
    delete_option('drnet_icalposts_blnpublishedposts');
    delete_option('drnet_icalposts_intshowposts');
}



add_action('admin_menu', 'drnet_icalposts_menu');


?>
