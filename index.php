<?php
function str_ends_with($haystack, $needle)
{
    return (substr($haystack, -strlen($needle)) === $needle) || $needle === '';
}

/* If the URL is too verbose (specifying index.php or page 1), then, of course
 * we just want the main page, which defaults to page 1 anyway. */
$url = parse_url($_SERVER['REQUEST_URI']);
if (strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || (isset($_GET['page']) && $_GET['page'] == 1)) {
    header("HTTP/1.1 301 Moved Permanently");
    $_SERVER['QUERY_STRING'] = str_replace('page=1', '', $_SERVER['QUERY_STRING']);
    header("Location: ./".($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : ''));
    exit;
} elseif (str_ends_with($url['path'], '/page/1') || str_ends_with($url['path'], '/page/1/')) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: ../".($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : ''));
    exit;
}

include_once('internal/Smarty.class.php');
$main_smarty = new Smarty;

include('config.php');
include(mnminclude.'html1.php');
include(mnminclude.'link.php');
include(mnminclude.'tags.php');
include(mnminclude.'search.php');
include(mnminclude.'smartyvariables.php');

// module system hook
$vars = '';
check_actions('index_top', $vars);

// find the name of the current category
if (isset($_REQUEST['category'])) {
    $thecat = get_cached_category_data('category_safe_name', sanitize($_REQUEST['category'], 1));

    $main_smarty->assign('request_category_name', $thecat->category_name);
    $catID = $thecat->category_id;
    $thecat = $thecat->category_name;
    if (!$thecat) {
        header("Location: $my_pligg_base/error_404.php");
        //$main_smarty->assign('tpl_center', 'error_404_center');
        //$main_smarty->display($the_template . '/pligg.tpl');
        die();
    }
}

// start a new search
$search = new Search();

// check for some get/post
if (isset($_REQUEST['from'])) {
    $search->newerthan = sanitize($_REQUEST['from'], 3);
}
unset($_REQUEST['search']);
unset($_POST['search']);
unset($_GET['search']);
if (isset($_REQUEST['search'])) {
    $search->searchTerm = sanitize($_REQUEST['search'], 3);
}
if (isset($_REQUEST['search'])) {
    $search->filterToStatus = "all";
}
if (!isset($_REQUEST['search'])) {
    $search->orderBy = "link_published_date DESC, link_date DESC";
}
if (isset($_REQUEST['tag'])) {
    $search->searchTerm = sanitize($_REQUEST['search'], 3);
    $search->isTag = true;
}
if (isset($thecat)) {
    $search->category = $catID;
}

// figure out what "page" of the results we're on
$search->offset = (get_current_page()-1)*$page_size;

// pagesize set in the admin panel
$search->pagesize = $page_size;

// on veut voir tous les posts les mieux classés de la semaine avec les sticky
$search->filterToStatus = "all";
$search->sticky = true;
$search->filterToTimeFrame = "today";
$search->orderBy = "link_votes DESC";

// this is for the tabs on the top that filter
if (isset($_GET['part'])) {
    $search->setmek = $db->escape($_GET['part']);
}
$search->do_setmek();

// do the search
$search->doSearch();

$linksum_count = $search->countsql;
$linksum_sql = $search->sql;

if (isset($_REQUEST['category'])) {
    $category_data = get_cached_category_data('category_safe_name', sanitize($_REQUEST['category'], 1));
    $main_smarty->assign('meta_description', $category_data->category_desc);
    $main_smarty->assign('meta_keywords', $category_data->category_keywords);

    // breadcrumbs and page title for the category we're looking at
    $main_smarty->assign('title', ''.$main_smarty->get_config_vars('PLIGG_Visual_Published_News').' - '.$thecat.'');
    $navwhere['text1'] = $main_smarty->get_config_vars('PLIGG_Visual_Published_News');
    $navwhere['link1'] = getmyurl('root', '');
    $navwhere['text2'] = $thecat;
    $main_smarty->assign('navbar_where', $navwhere);
    $main_smarty->assign('pretitle', $thecat);
    $main_smarty->assign('posttitle', $main_smarty->get_config_vars('PLIGG_Visual_Published_News'));
    $main_smarty->assign('page_header', $thecat.$main_smarty->get_config_vars('PLIGG_Visual_Published_News'));
    // pagename
    define('pagename', 'published');
    $main_smarty->assign('pagename', pagename);
} else {
    // breadcrumbs and page title
    $navwhere['show'] = 'yes';
    $navwhere['text1'] = $main_smarty->get_config_vars('PLIGG_Visual_Published_News');
    $navwhere['link1'] = getmyurl('root', '');
    $main_smarty->assign('navbar_where', $navwhere);
    $main_smarty->assign('posttitle', $main_smarty->get_config_vars('PLIGG_Visual_Home_Title'));
    $main_smarty->assign('page_header', $main_smarty->get_config_vars('PLIGG_Visual_Published_News'));
    // pagename
    define('pagename', 'index');
    $main_smarty->assign('pagename', pagename);
}

// On veut switcher sur la page new.php si le classement est changé.
if (isset($_GET['category'])) {
    $main_smarty->assign('index_url_recent', getmyurl('newcategory', sanitize($_GET['category'], 2)));
    $main_smarty->assign('index_url_today', getmyurl('new_sort', 'today', sanitize($_GET['category'], 2)));
    $main_smarty->assign('index_url_yesterday', getmyurl('new_sort', 'yesterday', sanitize($_GET['category'], 2)));
    $main_smarty->assign('index_url_week', getmyurl('new_sort', 'week', sanitize($_GET['category'], 2)));
    $main_smarty->assign('index_url_month', getmyurl('new_sort', 'month', sanitize($_GET['category'], 2)));
    $main_smarty->assign('index_url_year', getmyurl('new_sort', 'year', sanitize($_GET['category'], 2)));
    $main_smarty->assign('index_url_alltime', getmyurl('new_sort', 'alltime', sanitize($_GET['category'], 2)));
    $main_smarty->assign('index_url_upvoted', getmyurl('new_sort', 'upvoted', sanitize($_GET['category'], 2)));
    $main_smarty->assign('index_url_downvoted', getmyurl('new_sort', 'downvoted', sanitize($_GET['category'], 2)));
    $main_smarty->assign('index_url_commented', getmyurl('new_sort', 'commented', sanitize($_GET['category'], 2)));
    $main_smarty->assign('cat_url', getmyurl("newcategory"));
} else {
    $main_smarty->assign('index_url_recent', getmyurl('new'));
    $main_smarty->assign('index_url_today', getmyurl('new_sort', 'today'));
    $main_smarty->assign('index_url_yesterday', getmyurl('new_sort', 'yesterday'));
    $main_smarty->assign('index_url_week', getmyurl('new_sort', 'week'));
    $main_smarty->assign('index_url_month', getmyurl('new_sort', 'month'));
    $main_smarty->assign('index_url_year', getmyurl('new_sort', 'year'));
    $main_smarty->assign('index_url_alltime', getmyurl('new_sort', 'alltime'));
    $main_smarty->assign('index_url_upvoted', getmyurl('new_sort', 'upvoted'));
    $main_smarty->assign('index_url_downvoted', getmyurl('new_sort', 'downvoted'));
    $main_smarty->assign('index_url_commented', getmyurl('new_sort', 'commented'));
}

//  make sure my_base_url is set
if ($my_base_url == '') {
    echo '<div style="text-align:center;"><span class=error>ERROR: my_base_url is not set. Please correct this using the <a href = "/admin/admin_config.php?page=Location%20Installed">admin panel</a>. Then refresh this page.</span></div>';
}

// sidebar
$main_smarty = do_sidebar($main_smarty);
$sql = "SELECT user_login FROM ".table_users." ORDER BY user_id DESC LIMIT 1";
$last_user = $db->get_var($sql);
$main_smarty->assign('last_user', $last_user);

// misc smarty

if (isset($from_text)) {
    $main_smarty->assign('from_text', $from_text);
}

if (isset($search->setmek)) {
    $main_smarty->assign('setmeka', $search->setmek);
} else {
    $main_smarty->assign('setmeka', '');
}

$main_smarty->assign('URL_rss_page', getmyurl('rsspage', $category_data->category_safe_name, ''));

$fetch_link_summary = true;
include('./libs/link_summary.php'); // this is the code that show the links / stories

//For Infinit scrolling and continue reading option
if (Auto_scroll == 2 || Auto_scroll == 3) {
    $main_smarty->assign("scrollpageSize", $page_size);
} else {
    $main_smarty->assign('link_pagination', do_pages($rows, $page_size, "published", true));
}

// show the template
$main_smarty->assign('tpl_center', $the_template.'/index_center');
$main_smarty->display($the_template.'/pligg.tpl');
