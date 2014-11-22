<?php

/**
 * ECSHOP 会员等级管理程序
 * ============================================================================
 * 版权所有 2005-2010 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liuhui $
 * $Id: user_buy_rank.php 17063 2010-03-25 06:35:46Z liuhui $
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

$exc = new exchange($ecs->table('user_buy_rank'), $db, 'id', 'name');
$exc_user = new exchange($ecs->table("users"), $db, 'user_rank', 'user_rank');

/*------------------------------------------------------ */
//-- 会员等级列表
/*------------------------------------------------------ */

if ($_REQUEST['act'] == 'list')
{
    $ranks = array();
    $ranks = $db->getAll("SELECT * FROM " .$ecs->table('user_buy_rank'));

    $smarty->assign( 'ur_here',      $_LANG['06_revise_fee']);
    //$smarty->assign( 'action_link',  array('text' => $_LANG['add_user_rank'], 'href'=>'user_buy_rank.php?act=add'));
    $smarty->assign( 'full_page',    1);

    $smarty->assign( 'user_ranks',   $ranks);
    //$smarty->assign( 'action_link',  array('text' => "添加", 'href' => '?act=add'));

    assign_query_info();
    $smarty->display('revise_fee_list.htm');
}

/*------------------------------------------------------ */
//-- 翻页，排序
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    $ranks = array();
    $ranks = $db->getAll("SELECT * FROM " .$ecs->table('user_buy_rank'));
    $smarty->assign('user_ranks',   $ranks);
    make_json_result($smarty->fetch('revise_fee_list.htm'));
}

/*------------------------------------------------------ */
//-- 添加会员等级
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'add')
{
    admin_priv('user_rank');

    $rank['rank_id']      = 0;
    $rank['rank_special'] = 0;
    $rank['show_price']   = 1;
    $rank['min_points']   = 0;
    $rank['max_points']   = 0;
    $rank['discount']     = 100;

    $form_action          = 'insert';

    $smarty->assign('rank',        $rank);
    $smarty->assign('ur_here',     $_LANG['add_user_rank']);
    $smarty->assign('action_link', array('text' => $_LANG['05_user_rank_list'], 'href'=>'user_buy_rank.php?act=list'));
    $smarty->assign('ur_here',     $_LANG['add_user_rank']);
    $smarty->assign('form_action', $form_action);

    assign_query_info();
    $smarty->display('revise_fee_info.htm');
}

/*------------------------------------------------------ */
//-- 增加会员等级到数据库
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'insert')
{
    admin_priv('user_rank');

    $special_rank =  isset($_POST['special_rank']) ? intval($_POST['special_rank']) : 0;
    $_POST['min_points'] = empty($_POST['min_points']) ? 0 : intval($_POST['min_points']);
    $_POST['max_points'] = empty($_POST['max_points']) ? 0 : intval($_POST['max_points']);

    /* 检查是否存在重名的会员等级 */
    if (!$exc->is_only('id', trim($_POST['id'])))
    {
        sys_msg(sprintf($_LANG['rank_name_exists'], trim($_POST['rank_name'])), 1);
    }


    $sql = "INSERT INTO " .$ecs->table('user_buy_rank') ." ( ".
                "id, name, point".
            ") VALUES (".
                "'$_POST[id]','$_POST[name]','$_POST[point]' )";
    $db->query($sql);

    /* 管理员日志 */
    admin_log(trim($_POST['name']), 'add', 'user_buy_rank');
    clear_cache_files();

    $lnk[] = array('text' => $_LANG['back_list'],    'href'=>'user_buy_rank.php?act=list');
    $lnk[] = array('text' => $_LANG['add_continue'], 'href'=>'user_buy_rank.php?act=add');
    sys_msg($_LANG['add_rank_success'], 0, $lnk);
}
elseif($_REQUEST['act']=='edit')
{
     /* 权限判断 */
     
    admin_priv('user_rank');
    $sql = "SELECT * ".
            "FROM " .$ecs->table('user_buy_rank'). " WHERE id='$_REQUEST[id]'";
    $brand = $db->GetRow($sql);
    //print_r($sql);
    $smarty->assign('ur_here',     "编辑用户消费等级");
    $smarty->assign('action_link', array('text' => $_LANG['05_user_rank_list'], 'href' => '?act=list&' . list_link_postfix()));
    $smarty->assign('rank',       $brand);
    $smarty->assign('form_action', 'updata');

    assign_query_info();
   
    $smarty->display('revise_fee_info.htm');
}

elseif ($_REQUEST['act'] == 'updata')
{
    admin_priv('user_rank');

    $param = "revise_fee='$_POST[revise_fee]',free_times='$_POST[free_times]'";
 
	//die($param);
	$sql = "update ".$ecs->table('user_buy_rank')." set ".$param." where `id`='$_POST[old_id]'";
	//die($sql);
    if ($db->query($sql))
    {
        /* 清除缓存 */
        clear_cache_files();

        admin_log($_POST['name'], 'edit', '`revise_fee`');
        $link[0]['text'] = $_LANG['back_list'];
        $link[0]['href'] = '?act=list&' . list_link_postfix();
        $note = vsprintf($_LANG['rankedit_succed'], $_POST['name']);
        sys_msg($note, 0, $link);
    }
    else
    {
        die($db->error());
    }
}

/*------------------------------------------------------ */
//-- 删除会员等级
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('user_rank');

    $rank_id = intval($_GET['id']);

    if ($exc->drop($rank_id))
    {
        /* 更新会员表的等级字段 */
        $exc_user->edit("user_rank = 0", $rank_id);

        $rank_name = $exc->get_name($rank_id);
        admin_log(addslashes($rank_name), 'remove', 'user_buy_rank');
        clear_cache_files();
    }
    $url = '?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);
    ecs_header("Location: $url\n");
    exit;

}
/*
 *  编辑会员等级名称
 */
elseif ($_REQUEST['act'] == 'edit_name')
{
    $id = intval($_REQUEST['id']);
    $val = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));
    check_authz_json('user_rank');
    if ($exc->is_only('rank_name', $val, $id))
    {
        if ($exc->edit("rank_name = '$val'", $id))
        {
            /* 管理员日志 */
            admin_log($val, 'edit', 'user_rank');
            clear_cache_files();
            make_json_result(stripcslashes($val));
        }
        else
        {
            make_json_error($db->error());
        }
    }
    else
    {
        make_json_error(sprintf($_LANG['rank_name_exists'], htmlspecialchars($val)));
    }
}

/*
 *  ajax编辑积分下限
 */
elseif ($_REQUEST['act'] == 'edit_min_points')
{
    check_authz_json('user_rank');

    $rank_id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $val = empty($_REQUEST['val']) ? 0 : intval($_REQUEST['val']);

    $rank = $db->getRow("SELECT max_points, special_rank FROM " . $ecs->table('user_buy_rank') . " WHERE rank_id = '$rank_id'");
    if ($val >= $rank['max_points'] && $rank['special_rank'] == 0)
    {
        make_json_error($_LANG['js_languages']['integral_max_small']);
    }

    if ($rank['special_rank'] ==0 && !$exc->is_only('min_points', $val, $rank_id))
    {
        make_json_error(sprintf($_LANG['integral_min_exists'], $val));
    }

    if ($exc->edit("min_points = '$val'", $rank_id))
    {
        $rank_name = $exc->get_name($rank_id);
        admin_log(addslashes($rank_name), 'edit', 'user_rank');
        make_json_result($val);
    }
    else
    {
        make_json_error($db->error());
    }
}

/*
 *  ajax修改积分上限
 */
elseif ($_REQUEST['act'] == 'edit_max_points')
{
     check_authz_json('user_rank');

    $rank_id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $val = empty($_REQUEST['val']) ? 0 : intval($_REQUEST['val']);

    $rank = $db->getRow("SELECT min_points, special_rank FROM " . $ecs->table('user_buy_rank') . " WHERE rank_id = '$rank_id'");

    if ($val <= $rank['min_points'] && $rank['special_rank'] == 0)
    {
        make_json_error($_LANG['js_languages']['integral_max_small']);
    }

    if ($rank['special_rank'] ==0 && !$exc->is_only('max_points', $val, $rank_id))
    {
        make_json_error(sprintf($_LANG['integral_max_exists'], $val));
    }
    if ($exc->edit("max_points = '$val'", $rank_id))
    {
        $rank_name = $exc->get_name($rank_id);
        admin_log(addslashes($rank_name), 'edit', 'user_rank');
        make_json_result($val);
    }
    else
    {
        make_json_error($db->error());
    }
}

/*
 *  修改折扣率
 */
elseif ($_REQUEST['act'] == 'edit_discount')
{
    check_authz_json('user_rank');

    $rank_id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $val = empty($_REQUEST['val']) ? 0 : intval($_REQUEST['val']);

    if ($val < 1 || $val > 100)
    {
        make_json_error($_LANG['js_languages']['discount_invalid']);
    }

    if ($exc->edit("discount = '$val'", $rank_id))
    {
        $rank_name = $exc->get_name($rank_id);
         admin_log(addslashes($rank_name), 'edit', 'user_rank');
         clear_cache_files();
         make_json_result($val);
    }
    else
    {
        make_json_error($val);
    }
}

/*------------------------------------------------------ */
//-- 切换是否是特殊会员组
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'toggle_special')
{
    check_authz_json('user_rank');

    $rank_id       = intval($_POST['id']);
    $is_special    = intval($_POST['val']);

    if ($exc->edit("special_rank = '$is_special'", $rank_id))
    {
        $rank_name = $exc->get_name($rank_id);
        admin_log(addslashes($rank_name), 'edit', 'user_rank');
        make_json_result($is_special);
    }
    else
    {
        make_json_error($db->error());
    }
}
/*------------------------------------------------------ */
//-- 切换是否显示价格
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'toggle_showprice')
{
    check_authz_json('user_rank');

    $rank_id       = intval($_POST['id']);
    $is_show    = intval($_POST['val']);

    if ($exc->edit("show_price = '$is_show'", $rank_id))
    {
        $rank_name = $exc->get_name($rank_id);
        admin_log(addslashes($rank_name), 'edit', 'user_rank');
        clear_cache_files();
        make_json_result($is_show);
    }
    else
    {
        make_json_error($db->error());
    }
}

?>