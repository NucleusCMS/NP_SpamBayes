<?php
    //
    // Nucleus Admin section;
    // Created by Xiffy
    //
 	$strRel = '../../../';
	include($strRel . 'config.php');

	include($DIR_LIBS . 'PLUGINADMIN.php');

	if ($blogid) {$isblogadmin = $member->isBlogAdmin($blogid);}
	else $isblogadmin = 0;

	if (!($member->isAdmin() || $isblogadmin)) {
		$oPluginAdmin = new PluginAdmin('SpamBayes');
		$pbl_config = array();
		$oPluginAdmin->start();
		echo "<p>"._ERROR_DISALLOWED."</p>";
		$oPluginAdmin->end();
		exit;
	}


	if (isset($_GET['page'])) {$action = $_GET['page'];}
	if (isset($_POST['page'])) {$action = $_POST['page'];}

	// Okay; we are allowed. let's go
	// create the admin area page
	$oPluginAdmin = new PluginAdmin('SpamBayes');
	$oPluginAdmin->start();

	$action = requestVar('page');
	if ($action == 'clearlog') {
		$filter     = requestVar('filter')     ? requestVar('filter')     : 'all';
		$filtertype = requestVar('filtertype') ? requestVar('filtertype') : 'all';
		$ipp        = requestVar('ipp')		   ? requestVar('ipp')        : 10;
		$keyword	= requestVar('keyword');
		$oPluginAdmin->plugin->spambayes->nbs->clearlog($filter, $filtertype, $keyword, $ipp);
		$action = 'log';
		// reset values to no filter; otherwise the view will be empty
		if ($_REQUEST['amount'] <> 'cp' ) {
			$_REQUEST['filter']     = 'all';
			$_REQUEST['filtertype'] = 'all';
			$_REQUEST['keyword']    = '';
		}
	}

	sb_nucmenu($oPluginAdmin->plugin->getOption('enableTrainall'),$oPluginAdmin->plugin->getOption('enableLogging'));

	switch ($action) {
		case 'update':
			$oPluginAdmin->plugin->spambayes->updateProbabilities();
			break;
		case 'trainall':
			sb_trainall();
			break;
		case 'trainnew':
			sb_trainnew();
			break;
		case 'train':
			sb_train();
			sb_trainform();
			break;
		case 'untrain':
			sb_untrain();
			break;
		case 'trainlog':
			sb_trainlog();
			sb_log();
			break;
		case 'untrainall':
			sb_untrainall();
			break;
		case 'test':
			sb_test();
			sb_testform();
			break;
		case 'log':
			sb_log();
			break;
		case 'explain':
			sb_explain();
			sb_log();
			break;
		case 'batch':
			sb_batch();
			sb_log();
			break;
		case 'promote':
			sb_promote();
			sb_log();
			break;

	}

	$cats = $oPluginAdmin->plugin->spambayes->nbs->getCategories();
	$i = 0;
	$keys = array_keys($cats);
	echo '<fieldset><legend>Baysian DB statistics</legend><table>';
	echo '<tr><th>category</th><th>probability</th><th>wordcount</th></tr>';
	foreach($cats as $category) {
		echo "<tr><td><b>$keys[$i]</b></td>";
		foreach($category as $key => $value) {
			echo '<td>'.$value.'</td>';
		}
		echo '</tr>';
		$i++;
	}
	echo '</table></fieldset>';

	function getPluginOption($name) {
	    global $pbl_config;
	    return $pbl_config[$name];
	}
	function getPlugid() {
	    global $oPluginAdmin;
	    return $oPluginAdmin->plugin->plugid;
	}

	function sb_train() {
		global $oPluginAdmin;
		if (requestVar('catcode') > '' && requestVar('expression') > '') {
			$docid = $oPluginAdmin->plugin->spambayes->nbs->nextdocid();
			$oPluginAdmin->plugin->spambayes->train($docid,requestVar('catcode'),requestVar('expression'));
			$oPluginAdmin->plugin->spambayes->updateProbabilities();
		}
	}

	function sb_trainlog() {
		global $oPluginAdmin;
		if (requestVar('catcode') > '' && requestVar('id') > 0) {
			$query = 'select content from '.$oPluginAdmin->plugin->table_log.' where id = '.intval(requestVar('id'));
			$res = sql_query($query);
			$arr = mysql_fetch_array($res);
			if ($arr['content']) {
				$docid = $oPluginAdmin->plugin->spambayes->nbs->nextdocid();
				$oPluginAdmin->plugin->spambayes->train($docid,requestVar('catcode'),$arr['content']);
				$oPluginAdmin->plugin->spambayes->updateProbabilities();
				echo '<h3>document added to the database as: '.requestVar('catcode').'</h3>';
			} else {
				echo 'An error occured';
			}
		}
	}

	function sb_test() {
		global $oPluginAdmin;
		$expression = requestVar('expression');
		if ($expression > '') {
			$score = $oPluginAdmin->plugin->spambayes->categorize($expression);
			if ((float)$score['spam'] > (float)$oPluginAdmin->plugin->getOption('probability')) {
				echo '<h2>Testresult: Spam! [score:'.$score['spam'].']</h2>';
			} else {
				echo '<h2>Testresult: Ham! [score:'.$score['ham'].']</h2>';
			}
			echo '<fieldset style="width:90%;"><legend>Tested:</legend>';
			echo htmlentities($expression);
			echo '</fieldset>';
		}
	}

	function sb_trainall() {
		global $oPluginAdmin;
		// now train spam bayes with all current comments as ham!!
		$res = sql_query("select * from ".sql_table('comment'));
		while ($arr = mysql_fetch_array($res)) {
			$oPluginAdmin->plugin->spambayes->train($arr['cnumber'], 'ham', $arr['cbody'].' '.$arr['chost'].' '.$arr['cip']);
		}
		$oPluginAdmin->plugin->spambayes->updateProbabilities();
	}

	function sb_trainnew() {
		global $oPluginAdmin;
		// now train spam bayes with all current comments as ham!!
		$res = sql_query("select * from ".sql_table('comment'));
		while ($arr = mysql_fetch_array($res)) {
			$oPluginAdmin->plugin->spambayes->trainnew($arr['cnumber'], 'ham', $arr['cbody'].' '.$arr['chost'].' '.$arr['cip']);
		}
		$oPluginAdmin->plugin->spambayes->updateProbabilities();
	}

	function sb_untrainall() {
		global $oPluginAdmin;
		// now untrain spam bayes with all current comments as ham!!
		$res = sql_query("select * from ".sql_table('comment'));
		while ($arr = mysql_fetch_array($res)) {
			$oPluginAdmin->plugin->spambayes->untrain($arr['cnumber']);
		}
		$oPluginAdmin->plugin->spambayes->updateProbabilities();
	}

	function sb_untrain() {
		global $oPluginAdmin;
		if (requestVar('ref') > 0) {
			$oPluginAdmin->plugin->spambayes->untrain(requestVar('ref'));
			$oPluginAdmin->plugin->spambayes->updateProbabilities();
			echo '<h3>document untrained</h3>';
		}
		// build document table ...
		$startpos = requestVar('startpos') ? requestVar('startpos') : 0;
		$filterform = '<td></td>';
		$total = $oPluginAdmin->plugin->spambayes->nbs->countreftable();

		$pager = buildpager($startpos, $total, $filter, $filtertype, $filterform,'untrain', $keyword, 10);
		$res = $oPluginAdmin->plugin->spambayes->nbs->getreftable($startpos);

		echo '<h2>Spam Bayesian: Training data ['.$total.'] </h2>';
		echo '<table>';
		echo $pager;
		echo '<tr><th>Type</th><th>content</th><th>action</th></tr>';

		while ($arr = mysql_fetch_array($res)) {
			echo '<tr><td>'.$arr['catcode'].'</td><td>'.htmlentities($arr['content']).'</td><td><a href="'.$_SERVER['PHP_SELF'].'?page=untrain&amp;ref='.$arr['ref'].'">untrain</a></td></tr>';
		}
		echo $pager;
		echo '</table>';
	}

	function sb_explain(){
		global $oPluginAdmin;
		$id = requestVar('id');
		echo '<h2>Explain: Scorelog unweighed results (sorted on ham scores)</h2>';
		$arr = $oPluginAdmin->plugin->spambayes->nbs->getLogevent($id);

		$oPluginAdmin->plugin->spambayes->explain($arr['content']);
		echo '-- end report --';
	}

	function sb_promote(){
		global $oPluginAdmin;
		$id = requestVar('id');
		echo '<h2>Promoting to blog: '.$id.'</h2>';
		$arr = $oPluginAdmin->plugin->spambayes->nbs->getLogevent($id);
		$itemid = explode('itemid:', $arr['log']);
		$itemid = $itemid[1];
		echo 'itemid: '.$itemid.'<br />';
		$blogid = getBlogIDFromItemID($itemid);
		$comment = explode('^^',$arr['content']);

		$body		= addslashes($comment[0]);
		$host		= addslashes($comment[1]);
		$name		= addslashes($comment[2]);
		$url		= addslashes($comment[3]);
		$ip			= addslashes($comment[4]);
		$memberid	= 0;
		$timestamp	= $arr['logtime'];


		$query = 'INSERT INTO '.sql_table('comment').' (CUSER, CMAIL, CMEMBER, CBODY, CITEM, CTIME, CHOST, CIP, CBLOG) '
			   . "VALUES ('$name', '$url', $memberid, '$body', $itemid, '$timestamp', '$host', '$ip', '$blogid')";
		sql_query($query);
		echo '<b>comment added</b><br />';
		echo '-- end promote --';
	}

	function sb_batch() {
		global $oPluginAdmin;
		$logids = requestIntArray(batch);
		$action = requestVar('batchaction');
		debug: print_r ($logids);
		if ($logids) foreach ($logids as $id) {
			switch ($action) {
				case 'tspam':
				case 'tham':
					$ar = $oPluginAdmin->plugin->spambayes->nbs->getLogevent($id);
					$docid = $oPluginAdmin->plugin->spambayes->nbs->nextdocid();
					$cat = substr($action,1);
					$oPluginAdmin->plugin->spambayes->train($docid,$cat,$ar['content']);
					echo 'train '.$cat.': '.$id.'<br />';
					break;
				case 'delete':
					echo 'delete: '.$id.'<br />';
					$oPluginAdmin->plugin->spambayes->nbs->removeLogevent($id);
			}
		}

		echo '--end of batch--';
	}

	function sb_nucmenu($trainall, $logging) {
		global $oPluginAdmin;
		?>

		<!-- sorry, it's stronger then me :-) this javascript less popup's are styled using: http://meyerweb.com/eric/css/edge/popups/demo.html -->
		<style type="text/css">
			.adminmenu span {
				display:none;
			}
			.adminmenu a:hover span {
				display:block;
				position: absolute;
				text-decoration: none;
				top: 100px;
				left: 350px;
				width: 225px;
				background-color:#ffff7d;
				padding: 10px;
				font-weight: normal;
				font-size: 14px;
				border: 1px solid black;
				z-index: 100;
			}
			.adminmenu a:hover {
				background-color: #ffff7d;
			}
		</style>
		<?php
		$total      = $oPluginAdmin->plugin->spambayes->nbs->countlogtable('all');
	   	echo "<h2>SpamBayes menu</h2>\n";
		echo "<ul class=\"adminmenu\">\n";
		echo "<li><a href=\"".$_SERVER['PHP_SELF']."?page=train\">Spam Bayes training<span>Use this to train the Spam Bayesian filter with either 'ham' (not spam) or 'spam' messages. Your Bayessian filter needs both type of messages. The filter will become better with each message submitted.</span></a></li>\n";
		echo "<li><a href=\"".$_SERVER['PHP_SELF']."?page=untrain\">Spam Bayes untraining<span>Use this to remove references to a earlier trained document.</span></a></li>\n";
		if ($logging == 'yes') {
			echo "<li><a href=\"".$_SERVER['PHP_SELF']."?page=log\">Spam Bayes log ($total)<span>This page shows you the logging of Spam Bayes. You can browse through all 'ham' and 'spam' messages and train the filter with them if you like. (Especially usefull when SpamBayes got it wrong).</span></a></li>\n";
		}
		if ($trainall == 'yes') {
			echo "<li><a href=\"".$_SERVER['PHP_SELF']."?page=trainall\">Train HAM (not spam) with all comments<span>Use this to train the Spam Bayesian filter with all your comments as 'ham' (not spam). This can take a while but you don't have to do anything. Just sit back and relax. Once you've run this option it's save to remove it from the menu. (See options)</span></a></li>\n";
			echo "<li><a href=\"".$_SERVER['PHP_SELF']."?page=untrainall\">Remove all comments from the  HAM (not spam).<span>Use this to untrain the Spam Bayesian filter. This can take a while but you don't have to do anything. Just sit back and relax. Use only if you think earlier training went wrong.</span></a></li>\n";
		}
		echo "<li><a href=\"".$_SERVER['PHP_SELF']."?page=trainnew\">Train HAM (not spam) with all NEW comments<span>Use this to train the Spam Bayesian filter with all your yet untrained comments as 'ham' (not spam). This can take a while but you don't have to do anything. Just sit back and relax. You can use this option as much as you like. Only untrained comments will be added.</span></a></li>\n";
//		echo "<li><a href=\"".$_SERVER['PHP_SELF']."?page=update\">Update probabilities<span>After some training, you must use this to finalise</span></a></li>\n";
		echo "<li><a href=\"".$_SERVER['PHP_SELF']."?page=test\">Spam Bayes Test<span>Use this to test if a certain message would be considered 'ham' (not spam) or 'spam' message</span></a></li>\n";
		echo "<li><a href=\"".dirname($_SERVER['PHP_SELF'])."/../../index.php?action=pluginoptions&amp;plugid=".getPlugid()."\">Spam Bayes options<span>This will take you to the plugins options page. This menu is NOT available on that page. Sorry for this. Use the quickmenu option to show a quicklink to the admin page!</span></a></li>\n";
		echo "</ul>\n";
	}

	function sb_log() {
		global $oPluginAdmin;
		$startpos   = requestVar('startpos')   ? requestVar('startpos')   : 0;
		$filter     = requestVar('filter')     ? requestVar('filter')     : 'all';
		$filtertype = requestVar('filtertype') ? requestVar('filtertype') : 'all';
		$ipp        = requestVar('ipp')        ? requestVar('ipp')        : 10;
		$keyword    = requestVar('keyword');
		$filterform = buildfilterform($filter,$filtertype,$keyword,$ipp);

		$total      = $oPluginAdmin->plugin->spambayes->nbs->countlogtable($filter, $filtertype, $keyword);
		if ($filter == 'all') {
			$htotal = $oPluginAdmin->plugin->spambayes->nbs->countlogtable('ham',$filtertype, $keyword);
			$stotal = $oPluginAdmin->plugin->spambayes->nbs->countlogtable('spam',$filtertype, $keyword);
			echo '<h2>Spam Bayesian: Log [total events: '.$total.' (ham: '.$htotal.' spam: '.$stotal.') ]</h2>';
		} else {
			echo '<h2>Spam Bayesian: Log [total '.$filter.' events: '.$total.']</h2>';
		}

		$res = $oPluginAdmin->plugin->spambayes->nbs->getlogtable($startpos,$filter, $filtertype, $keyword, $ipp);
		$pager = buildpager($startpos, $total, $filter, $filtertype, $filterform,'log', $keyword, $ipp);
		if ($total % $ipp == 0) {
			$ap = intval(floor($total / $ipp));
		} else {
			$ap = intval(floor($total / $ipp)) + 1;
		}
		$cp = intval($startpos + $ipp) / $ipp;
		echo '<table>';
		echo '<tr><th colspan="2">Page '.$cp.' of '.$ap.'</th><td colspan="2">Browse: <form style="display:inline"><input type="text" size="3" name="ipp" value="'.$ipp.'" /> items per page. <input type="submit" value="Go" /><input type="hidden" name="amount" value="cp" /><input type="hidden" name="filter" value="'.$filter.'" /><input type="hidden" name="filtertype" value="'.$filtertype.'" /><input type="hidden" name="keyword" value="'.$keyword.'" /><input type="hidden" name="page" value="log" /></form>';
		echo '<span style="text-align:right" class="batchoperations">';
		if ($filter <> 'all') {
			echo ' type: <b>'.$filter.'</b>';
		}
		if ($filtertype <> 'all') {
			echo ' event: <b>'.$filtertype.'</b>';
		}
		if ($keyword > '') {
			echo ' keyword: <b>'.$keyword.'</b>';
		}
		echo '</span></td></tr>';
		echo $pager;
		$extraaction = '&amp;filter='.$filter.'&amp;filtertype='.urlencode($filtertype).'&amp;startpos='.$startpos.'&amp;keyword='.$keyword.'&amp;ipp='.$ipp;
		echo '<tr><th>Date</th><th>event</th><th>content</th><th>action</th></tr><form>';
		$i = 0;
		while ($arr = mysql_fetch_array($res)) {
			echo '<tr onmouseover="focusRow(this);" onmouseout="blurRow(this);"><td>'.$arr['logtime'].'<br /><b>'.$arr['catcode'].'</b></td><td>'.$arr['log'].'</td><td><input id="batch'.$i.'" name="batch['.$i.']" value="'.$arr['id'].'" type="checkbox"><label for="batch'.$i.'">'.htmlentities(str_replace('^^', ' ',$arr['content'])).'</label></td>';
			echo '<td><a href="'.$_SERVER['PHP_SELF'].'?page=trainlog&amp;catcode=ham&amp;id='.$arr['id'].$extraaction.'"><nobr>train ham</nobr></a>';
			echo ' <a href="'.$_SERVER['PHP_SELF'].'?page=trainlog&amp;catcode=spam&amp;id='.$arr['id'].$extraaction.'"><nobr>train spam</nobr></a>';
			echo '<br /><a href="'.$_SERVER['PHP_SELF'].'?page=explain&amp;id='.$arr['id'].$extraaction.'"><nobr>explain</nobr></a>';
			if (strstr($arr['log'], 'itemid:')) {
				 echo '<br /><br /><a style="color:red" href="'.$_SERVER['PHP_SELF'].'?page=promote&amp;id='.$arr['id'].$extraaction.'"><nobr>publish</nobr></a>';
			}
			echo '</td>';
			echo '</tr>';
			$i++;
		}
		if (mysql_num_rows($res) == 0) {
			echo '<tr><td colspan="4"><b>Eventlog is empty</b></td></tr>';
		}
		echo '<tr><td colspan="4"><div class="batchoperations">with selected:<select name="batchaction">';
		echo '<option value="tspam">Train spam</option>';
		echo '<option value="tham">Train ham</option>';
		echo '<option value="delete">Delete</option></select><input name="page" value="batch" type="hidden">';
		echo '<input type="hidden" name="ipp" value="'.$ipp.'"/><input type="hidden" name="filter" value="'.$filter.'" /><input type="hidden" name="filtertype" value="'.$filtertype.'" /><input type="hidden" name="keyword" value="'.$keyword.'" />';
		echo '<input value="Submit" type="submit">(
			 <a href="" onclick="if (event && event.preventDefault) event.preventDefault(); return batchSelectAll(1); ">select all</a> -
			 <a href="" onclick="if (event && event.preventDefault) event.preventDefault(); return batchSelectAll(0); ">deselect all</a>
			)
		</div></td></tr></form>';
		echo '<tr><td colspan="4"><div class="batchoperations"><form action="" method="get" style="display:inline"><input type="hidden" name="ipp" value="'.$ipp.'"/><input type="hidden" name="page" value="clearlog" /><input type="hidden" name="amount" value="cp" /><input type="hidden" name="filter" value="'.$filter.'" /><input type="hidden" name="filtertype" value="'.$filtertype.'" /><input type="hidden" name="keyword" value="'.$keyword.'" /><input type="submit" value="Clear first '.$ipp.'" /></form> <form action="" method="get" style="display:inline"><input type="hidden" name="ipp" value="'.$ipp.'"/><input type="hidden" name="page" value="clearlog" /><input type="hidden" name="amount" value="cf" /><input type="hidden" name="filter" value="'.$filter.'" /><input type="hidden" name="filtertype" value="'.$filtertype.'" /><input type="hidden" name="keyword" value="'.$keyword.'" /><input type="submit" value="Clear current filtered logs" /></form> <form action="" method="get" style="display:inline"><input type="hidden" name="page" value="clearlog" /><input type="submit" value="Clear complete log" /></form></div></td></tr>';
		echo '<tr><th colspan="2">Page '.$cp.' of '.$ap.'</th><td colspan="2">Browse: <form style="display:inline"><input type="text" size="3" name="ipp" value="'.$ipp.'" /> items per page. <input type="submit" value="Go" /><input type="hidden" name="amount" value="cp" /><input type="hidden" name="filter" value="'.$filter.'" /><input type="hidden" name="filtertype" value="'.$filtertype.'" /><input type="hidden" name="keyword" value="'.$keyword.'" /><input type="hidden" name="page" value="log" /></form></td></tr>';
		echo $pager;
		echo '</table>';
	}

	function sb_trainform() {
		echo "<form action=\"".$_SERVER['PHP_SELF']."\" method=\"get\">\n";
		echo "<input type=\"hidden\" name=\"page\" value=\"train\" />\n";
		echo "<select name=\"catcode\"><option value=\"ham\">Ham (not spam)</option><option value=\"spam\" selected=\"1\">Spam</option></select><br />";
    	echo "<textarea class=\"sb_textinput\" cols=\"60\" rows=\"6\" name=\"expression\" ></textarea><br />";
		echo "<input type=\"submit\" value=\"Train\" />\n";
		echo "</form>\n";
	}

	function sb_testform() {
		echo "<h2>Enter a message that needs to be tested against Spam Bayes</h2>";
		echo "<form action=\"".$_SERVER['PHP_SELF']."\" method=\"get\">\n";
		echo "<input type=\"hidden\" name=\"page\" value=\"test\" />\n";
    	echo "<textarea class=\"sb_textinput\" cols=\"60\" rows=\"6\" name=\"expression\" ></textarea><br />";
		echo "<input type=\"submit\" value=\"Test this!\" />\n";
		echo "</form>\n";
	}

	function buildpager($startpos, $total, $filter, $filtertype, $filterform, $action, $keyword, $ipp) {
		$pager = '<tr>';
		if ($startpos >= $ipp) {
			$pager .= '<td><form action="" method="get" style="display:inline"><input type="hidden" name="page" value="'.$action.'" />';
			$pager .= '<input type="hidden" value="'.($startpos - $ipp).'" name="startpos" /><input type="submit" value="Previous page" /><input type="hidden" name="filter" value="'.$filter.'" /><input type="hidden" name="filtertype" value="'.$filtertype.'" /><input type="hidden" name="keyword" value="'.$keyword.'" /><input type="hidden" name="ipp" value="'.$ipp.'"/></form></td>'.$filterform;
		} else {
			$pager .= '<td></td>'.$filterform;
		}
		if (($total - $ipp) > $startpos) {
			$pager .= '<td><form action="" method="get" style="display:inline"><input type="hidden" name="page" value="'.$action.'" />';
			$pager .= '<input type="hidden" value="'.($startpos + $ipp).'" name="startpos" /><input type="submit" value="Next page" /><input type="hidden" name="filter" value="'.$filter.'" /><input type="hidden" name="filtertype" value="'.$filtertype.'" /><input type="hidden" name="keyword" value="'.$keyword.'" /><input type="hidden" name="ipp" value="'.$ipp.'"/></form></td>';
		} else {
			$pager .= '<td></td>';
		}
		$pager .= '</tr>';
		return $pager;
	}

	function buildfilterform($filter,$filtertype, $keyword, $ipp) {
		global $oPluginAdmin;
		$selected   = $filter == 'all' ? 'selected':'';
		$filterform = '<td colspan="2"><form style="display:inline">Show: <select name="filter"><option value="all" '.$selected.'>All</option>';
		$selected   = $filter == 'ham' ? 'selected':'';
		$filterform .= '<option value="ham" '.$selected.'>Ham (not spam)</option>';
		$selected   = $filter == 'spam' ? 'selected':'';
		$filterform .= '<option value="spam" '.$selected.'>Spam</option></select> <input type="hidden" name="page" value="log"/><input type="hidden" name="ipp" value="'.$ipp.'"/>';

		$logtypes   = $oPluginAdmin->plugin->spambayes->nbs->getlogtypes();
		$selected   = $filtertype == 'all' ? 'selected':'';
		$filterform .= '<select name="filtertype"><option value="all" '.$selected.'>All events</option>';
		foreach($logtypes as $logtype) {
			$selected = $filtertype == $logtype ? 'selected' : '';
			$show = explode(' ',$logtype);
			$show = $show[0] == 'event' ? $show[1] : $show[0];
			$filterform .= '<option value="'.$logtype.'" '.$selected.'>'.$show.'</option>';
		}
		$filterform .= '</select><input type="submit" value="Apply filter" /></form>';
		$filterform .= '&nbsp;|&nbsp;<form style="display:inline"><input type="hidden" name="page" value="log"/><input type="hidden" name="filter" value="'.$filter.'"/><input type="hidden" name="filtertype" value="'.$filtertype.'"/><input type="hidden" name="ipp" value="'.$ipp.'"/><input type="text" name="keyword" value="'.$keyword.'" /><input type="submit" value="search" /></form>';
		$filterform .= '</td>';
		return $filterform;
	}
	$oPluginAdmin->end();
