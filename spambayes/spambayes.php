<?php
/****** BEGIN LICENSE BLOCK *****
	This file is part of PHP Naive Bayesian Filter.
	The Initial Developer of the Original Code is
	Loic d'Anterroches [loic_at_xhtml.net].
	Portions created by the Initial Developer are Copyright (C) 2003
	the Initial Developer. All Rights Reserved.

	PHP Naive Bayesian Filter is free software; you can redistribute it
	and/or modify it under the terms of the GNU General Public License as
	published by the Free Software Foundation; either version 2 of
	the License, or (at your option) any later version.

	PHP Naive Bayesian Filter is distributed in the hope that it will
	be useful, but WITHOUT ANY WARRANTY; without even the implied
	warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
	See the GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Foobar; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

	Alternatively, the contents of this file may be used under the terms of
	the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
	in which case the provisions of the LGPL are applicable instead
	of those above.
	  ***** END LICENSE BLOCK *****
*/


class NaiveBayesian {
	/** min token length for it to be taken into consideration */
	var $min_token_length = 3;
	/** max token length for it to be taken into consideration */
	var $max_token_length = 40;
	/** list of token to ignore @see getIgnoreList() */
	var $ignore_list = array();

    var $nbs = null;

	function NaiveBayesian(&$parent) {
		$this->nbs = new NaiveBayesianStorage(&$parent);
		$this->parent = &$parent;
		return true;
	}

	/** categorize a document.
		Get list of categories in which the document can be categorized
		with a score for each category.

		@return array keys = category ids, values = scores
		@param string document
	*/
	function categorize($document) {
		$scores = array();
		$categories = $this->nbs->getCategories();

		$tokens = $this->_getTokens($document);
		// calculate the score in each category
		$total_words = 0;
		$ncat = 0;
		while (list($category, $data) = each($categories)) {
			$total_words += $data['wordcount'];
			$ncat++;
		}
		reset($categories);
		while (list($category, $data) = each($categories)) {
			$scores[$category] = $data['probability'];
			//debug: print_r($scores);
			// small probability for a word not in the category
			// maybe putting 1.0 as a 'no effect' word can also be good
			$small_proba = 1.0 / ($data['wordcount'] * 2);
			reset($tokens);
			while (list($token, $count) = each($tokens)) {
				//debug: echo "<br/>$token; $count ";
				if ($this->nbs->wordExists($token)) {
					//debug: echo "$category = known $small_proba wordcount: ";
					$word = $this->nbs->getWord($token, $category);
					//debug: echo $word['wordcount'];
					if ($word['wordcount']) $proba = $word['wordcount']/$data['wordcount'];
					else $proba = $small_proba;
					$newval = $scores[$category] * pow($proba, $count)*pow($total_words/$ncat, $count);
					if (is_finite($newval)) {
						$scores[$category] = $newval;
					}
				}
			}
		} // while (list () )
		return $this->_rescale($scores);
	} // function categorize


	function explain($content) {
		$categories = $this->nbs->getCategories(); // ham, spam
		$scores = array();
		$tokens = $this->_getTokens($content);
		// calculate the score in each category
		$total_words = 0;
		$ncat = 0;
		while (list($category, $data) = each($categories)) {
			$total_words += $data['wordcount'];
			$ncat++;
		}
		reset($categories);
		$result = array();
		while (list($category, $data) = each($categories)) {
			$scores[$category] = $data['probability'];
			//debug: echo $category.'<br />';
			$small_proba = 1.0 / ($data['wordcount'] * 2);
			reset($tokens);
			//print_r ($tokens);
			while (list($token, $count) = each($tokens)) {
				//debug:
				//echo "<br/>$token; $count ";
				if ($this->nbs->wordExists($token)) {
					$word = $this->nbs->getWord($token, $category);
					$result[$word['word']][$category] = $word['wordcount'];
					//print_r($word);
					//echo "<br />\n";
					if ($word['wordcount']) $proba = $word['wordcount']/$data['wordcount'];
					else $proba = $small_proba;
					$newval = $scores[$category] * pow($proba, $count)*pow($total_words/$ncat, $count);
					if (is_finite($newval)) {
						$scores[$category] = $newval;
					}
				}
			}
		}
		$scores = $this->_rescale($scores);
		array_multisort($result, SORT_DESC);

		echo '<table>';
		echo '<tr><th>word</th><th>Ham</th><th>Spam</th></tr>';
		foreach($result as $key => $value) {
			echo '<tr>';
			echo '<td>'.$key.'</td>';
			echo '<td>'.$value['ham'].'</td>';
			echo '<td>'.$value['spam'].'</td>';
			echo '</tr>';
		}
		echo '<tr><td>Rescaled probability:</td><th>'.$scores['ham'].'</th><th>'.$scores['spam'].'</th></tr>';
		echo '</table>';
		//debug: print_r ($scores);
	}

	/** training against a document.
		Set a document as being in a specific category. The document becomes a reference
		and is saved in the table of references. After a set of training is done
		the updateProbabilities() function must be run.

		@see updateProbabilities()
		@see untrain()
		@return bool success
		@param string document id, must be unique
		@param string category_id the category id in which the document should be
		@param string content of the document
	*/
	function train($doc_id, $category_id, $content) {
		$tokens = $this->_getTokens($content);
		//debug: print_r($tokens);
		while (list($token, $count) = each($tokens)) {
			$this->nbs->updateWord($token, $count, $category_id);
		}
		$this->nbs->saveReference($doc_id, $category_id, $content);
		return true;
	} // function train

	function trainnew($doc_id, $category_id, $content) {
		$reference = $this->nbs->getReference($doc_id);
		if (!$reference) {
			$this->train($doc_id, $category_id, $content);
		}
	}

	/** untraining of a document.
		To remove just one document from the references.

		@see updateProbabilities()
		@see untrain()
		@return bool success
		@param string document id, must be unique
	*/

	function untrain($doc_id) {
		$ref = $this->nbs->getReference($doc_id);
		$tokens = $this->_getTokens($ref['content']);
		while (list($token, $count) = each($tokens)) {
			$this->nbs->removeWord($token, $count, $ref['catcode']);
		}
		$this->nbs->removeReference($doc_id);
		return true;
	} // function untrain

	/** rescale the results between 0 and 1.
		@author Ken Williams, ken@mathforum.org
		@see categorize()
		@return array normalized scores (keys => category, values => scores)
		@param array scores (keys => category, values => scores)
	*/

	function _rescale($scores) {
		// Scale everything back to a reasonable area in
		// logspace (near zero), un-loggify, and normalize
		$total = 0.0;
		$max   = 0.0;
		reset($scores);
		while (list($cat, $score) = each($scores)) {
			if ($score >= $max) $max = $score;
		}
		reset($scores);
		while (list($cat, $score) = each($scores)) {
			$scores[$cat] = (float) exp($score - $max);
			$total += (float) pow($scores[$cat],2);
		}
		$total = (float) sqrt($total);
		reset($scores);
		while (list($cat, $score) = each($scores)) {
			$scores[$cat] = (float) $scores[$cat]/$total;
		}
		reset($scores);
		return $scores;
	} // function _rescale

	/** update the probabilities of the categories and word count.
		This function must be run after a set of training

		@see train()
		@see untrain()
		@return bool sucess
	*/
	function updateProbabilities() {
		// this function is really only database manipulation
		// that is why all is done in the NaiveBayesianStorage
		return $this->nbs->updateProbabilities();
	} // function updateProbabilities

	/** Get the list of token to ignore.
		@return array ignore list
	*/

	function getIgnoreList() {
		$ignore = $this->parent->getOption('ignorelist');
		$arr = explode(',',$ignore);
		$ignore = implode(' ',$arr);
		$arr = explode(' ',$ignore);
		return $arr;
	}

	/** get the tokens from a string
		@author James Seng. [http://james.seng.cc/] (based on his perl version)

		@return array tokens
		@param  string the string to get the tokens from
	*/
	function _getTokens($string)  {
		$rawtokens = array();
		$tokens    = array();
		$string = $this->_cleanString($string);
		if (count(0 >= $this->ignore_list))
			$this->ignore_list = $this->getIgnoreList();
		$rawtokens = split("[^-_A-Za-z0-9]+", $string);
		// remove some tokens
		while (list( , $token) = each($rawtokens)) {
			$token = trim($token);
			if (!(('' == $token)                             ||
				  (strlen($token) < $this->min_token_length) ||
				  (strlen($token) > $this->max_token_length) ||
				  (preg_match('/^[0-9]+$/', $token))         ||
				  (in_array($token, $this->ignore_list))
				))
				$tokens[$token]++;
		} // while
		return $tokens;
	} // function _getTokens

	/** clean a string from the diacritics
		@author Antoine Bajolet [phpdig_at_toiletoine.net]
		@author SPIP [http://uzine.net/spip/]

		@return string clean string
		@param  string string with accents
	*/

	function _cleanString($string)  {
		$diac =
			/* A */   chr(192).chr(193).chr(194).chr(195).chr(196).chr(197).
			/* a */   chr(224).chr(225).chr(226).chr(227).chr(228).chr(229).
			/* O */   chr(210).chr(211).chr(212).chr(213).chr(214).chr(216).
			/* o */   chr(242).chr(243).chr(244).chr(245).chr(246).chr(248).
			/* E */   chr(200).chr(201).chr(202).chr(203).
			/* e */   chr(232).chr(233).chr(234).chr(235).
			/* Cc */  chr(199).chr(231).
			/* I */   chr(204).chr(205).chr(206).chr(207).
			/* i */   chr(236).chr(237).chr(238).chr(239).
			/* U */   chr(217).chr(218).chr(219).chr(220).
			/* u */   chr(249).chr(250).chr(251).chr(252).
			/* yNn */ chr(255).chr(209).chr(241);
		return strtolower(strtr($string, $diac, 'AAAAAAaaaaaaOOOOOOooooooEEEEeeeeCcIIIIiiiiUUUUuuuuyNn'));
	}
} // class NaiveBaysian


/** Access to the storage of the data for the filter.

To avoid dependency with respect to any database, this class handle all the
access to the data storage. You can provide your own class as long as
all the methods are available. The current one rely on a MySQL database.

methods:
    - array getCategories()
    - bool  wordExists(string $word)
    - array getWord(string $word, string $categoryid)

*/
class NaiveBayesianStorage {
	function NaiveBayesianStorage(&$plugin) {
		$this->table_cat = sql_table('plug_sb_cat'); // categories
		$this->table_wf  = sql_table('plug_sb_wf');  // word frequencies
		$this->table_ref = sql_table('plug_sb_ref'); // references
		$this->table_log = sql_table('plug_sb_log'); // logging
		$this->plugin = &$plugin;
	}
	/** get the list of categories with basic data.
		@return array key = category ids, values = array(keys = 'probability', 'word_count')
	*/
	function getCategories() {
		$categories = array();

		$rs = sql_query('SELECT * FROM '.$this->table_cat);

		if ($rs) {
			while ($row = mysql_fetch_array($rs)) {
				$categories[$row['catcode']] = array('probability' => $row['probability'], 'wordcount'  => $row['wordcount'] );
			}
		} else {
			$categories[0] = 'No categories found';
		}
		return $categories;
	} // getCategories

	/** see if the word is an already learnt word.
		@return bool
		@param string word
	*/
	function wordExists($word)  {
		$rs = sql_query("SELECT count(*) as amount FROM ".$this->table_wf." WHERE word='". mysql_real_escape_string($word)."'");
        $obj = mysql_fetch_object($rs);
        if ($obj->amount == 0) return false;
        else return true;
    } // wordExists

    /** get details of a word in a category.
    	@return array ('count' => count)
    	@param  string word
    	@param  string category id
    */
    function getWord($word, $catcode){
    	$details = array();
    	$rs = sql_query("SELECT * FROM ".$this->table_wf." WHERE word='".mysql_real_escape_string($word)."' AND catcode='".mysql_real_escape_string($catcode)."'");
    	$obj = mysql_fetch_object($rs);
    	if ($obj) {
    		$details['wordcount'] = $obj->wordcount;
    		$details['catcode']   = $obj->catcode;
    		$details['word']      = $obj->word;
    	} else {
    		$details['wordcount'] = 0;
    		$details['catcode']   = $catcode;
    		$details['word']      = $word;
    	}
		return $details;
	} // getWord

	/** update a word in a category.
		If the word is new in this category it is added, else only the count is updated.
		@return bool success
		@param string word
		@param int    count
		@paran string category id
	*/

	function updateWord($word, $wordcount, $catcode) {
		$oldword = $this->getWord($word, $catcode);
		if (0 == $oldword['wordcount']) {
			return sql_query("INSERT INTO ".$this->table_wf." (word, catcode, wordcount) VALUES ('".mysql_real_escape_string($word)."','".mysql_real_escape_string($catcode)."','".mysql_real_escape_string((int)$wordcount)."')");
		} else {
			return sql_query("UPDATE ".$this->table_wf." SET wordcount = wordcount +".(int)$wordcount." WHERE catcode = '".mysql_real_escape_string($catcode)."' AND word = '".mysql_real_escape_string($word)."'");
		}
	} // function updateWord

	/** remove a word from a category.
		@return bool success
		@param string word
		@param int  count
		@param string category id
	*/

	function removeWord($word, $wordcount, $catcode) {
		$oldword = $this->getWord($word, $catcode);
		if (0 != $oldword['wordcount'] && 0 >= ($oldword['wordcount']-$wordcount)) {
			return sql_query("DELETE FROM ".$this->table_wf." WHERE word='".mysql_real_escape_string($word)."' AND catcode ='".mysql_real_escape_string($catcode)."'");
		} else {
			return sql_query("UPDATE ".$this->table_wf." SET wordcount = wordcount - ".(int)$wordcount." WHERE catcode = '".mysql_real_escape_string($catcode)."' AND word = '".mysql_real_escape_string($word)."'");
		}
	} // function removeWord

	/** update the probabilities of the categories and word count.
		This function must be run after a set of training
		@return bool sucess
	*/
	function updateProbabilities() {
		// first update the word count of each category
		$rs = sql_query("SELECT catcode, SUM(wordcount) AS total FROM ".$this->table_wf." WHERE 1 GROUP BY catcode");
		$total_words = 0;
		while ($obj = mysql_fetch_object($rs)) {
			$total_words += $obj->total;
		}

		if ($total_words == 0) {
			sql_query("UPDATE ".$this->table_cat." SET wordcount = 0, probability = 0 WHERE 1");
		} else {
			$rs = sql_query("SELECT catcode, SUM(wordcount) AS total FROM ".$this->table_wf." WHERE 1 GROUP BY catcode");
			while ($obj = mysql_fetch_object($rs)) {
				$proba = $obj->total / $total_words;
				sql_query("UPDATE ".$this->table_cat." SET wordcount=".(int)$obj->total.", probability=".$proba." WHERE catcode = '".$obj->catcode."'");
			}
		}
		return true;
	} // updateProbabilities

	/** save a reference in the database.
		@return bool success
		@param  string reference if, must be unique
		@param  string category id
		@param  string content of the reference
	*/
	function saveReference($ref, $catcode, $content) {
		return sql_query("INSERT INTO ".$this->table_ref." (ref, catcode, content) VALUES (".intval($ref).", '".mysql_real_escape_string($catcode)."','".mysql_real_escape_string($content)."')");
	} // function saveReference

	/** get a reference from the database.
		@return array  reference( catcode => ...., content => ....)
		@param  string id
	*/
	function getReference($ref) {
		$reference = array();
		$rs = sql_query("SELECT * FROM ".$this->table_ref." WHERE ref=".intval($ref));
		if ($rs) {
			$reference = mysql_fetch_array($rs);
		}
		return $reference;
	}

	/** remove a reference from the database
		@return bool sucess
		@param  string reference id
	*/

	function removeReference($ref) {
		return sql_query("DELETE FROM ".$this->table_ref." WHERE ref=".intval($ref));
	}

	function nextdocid() {
		$res = sql_query ("select ref from ".$this->table_ref." order by ref desc limit 0,1");
		$obj = mysql_fetch_object($res);
		if ($obj->ref >= 1000000) {
			return $obj->ref + 1;
		} else {
			return 1000000;
		}
	}

	function logevent($log,$content,$catcode) {
		if ($this->plugin->getOption('enableLogging') == 'yes') {
			if (isset($log) && isset($content)) {
				sql_query("insert into ".$this->table_log." (log,content,catcode) values ('".mysql_real_escape_string($log)."','".mysql_real_escape_string($content)."','".mysql_real_escape_string($catcode)."')");
			}
		}
	} // logevent

	function clearlog($filter = 'all', $filtertype = 'all', $keyword = '', $ipp = 10) {
		$query = 'delete from '.$this->table_log;
		if ($filter != 'all' || $filtertype != 'all') {
			$query .= ' where ';
			if ($filter != 'all') {
				$query .= " catcode = '".mysql_real_escape_string($filter)."'";
			}
			if ($filter != 'all' && $filtertype != 'all') {
				$query .= ' and ';
			}
			if ($filtertype != 'all') {
				$query .= " log like '".mysql_real_escape_string($filtertype)."%'";
			}
			if ($keyword != '') {
				$query .= " and content like '%".mysql_real_escape_string($keyword)."%'";
			}
		} elseif ($keyword != '') {
			$query .= " where content like '%".mysql_real_escape_string($keyword)."%'";
		}
		if ($_REQUEST['amount'] == 'cp') { //only current page?
			$query .= '  order by logtime desc limit '.$ipp;
		}
		sql_query($query);
	} // function clearlog

	function getlogtable($startpos, $filter = 'all',$filtertype = 'all', $keyword, $ipp = 10) {
		$query = 'select * from '.$this->table_log;
		if ($filter != 'all' || $filtertype != 'all') {
			$query .= ' where ';
			if ($filter != 'all') {
				$query .= " catcode = '".mysql_real_escape_string($filter)."'";
			}
			if ($filter != 'all' && $filtertype != 'all') {
				$query .= ' and ';
			}
			if ($filtertype != 'all') {
				$query .= " log like '".mysql_real_escape_string($filtertype)."%'";
			}
			if ($keyword != '') {
				$query .= " and content like '%".mysql_real_escape_string($keyword)."%'";
			}
		} elseif ($keyword != '') {
			$query .= " where content like '%".mysql_real_escape_string($keyword)."%'";
		}
		$query .= ' order by logtime desc limit '.$startpos.','.$ipp;
		return sql_query($query);
	} // function getlogtable

	function countlogtable($filter = 'all', $filtertype = 'all', $keyword = '') {
		$query = 'select count(*) as total from '.$this->table_log;
		if ($filter != 'all' || $filtertype != 'all') {
			$query .= ' where ';
			if ($filter != 'all') {
				$query .= " catcode = '".mysql_real_escape_string($filter)."'";
			}
			if ($filter != 'all' && $filtertype != 'all') {
				$query .= ' and ';
			}
			if ($filtertype != 'all') {
				$query .= " log like '".mysql_real_escape_string($filtertype)."%'";
			}
			if ($keyword != '') {
				$query .= " and content like '%".mysql_real_escape_string($keyword)."%'";
			}
		} elseif ($keyword != '') {
			$query .= " where content like '%".mysql_real_escape_string($keyword)."%'";
		}
		$res = sql_query($query);
		$arr = mysql_fetch_array($res);
		return $arr['total'];
	}

	function getlogtypes() {
		$query = "select distinct(substring_index(log,' ', 2)) as logtype from ".$this->table_log;
		$logtypes = array();
		$res = sql_query($query);
		while ($arr = mysql_fetch_array($res)) {
			$logtypes[] = $arr['logtype'];
		}
		return $logtypes;
	}

	function getreftable($startpos) {
		$query = 'select * from '.$this->table_ref.' where ref >= 1000000 order by ref desc limit '.$startpos.',10';
		return sql_query($query);
	}

	function getLogevent($id) {
		$query = 'select * from '.$this->table_log.' where id = '.$id;
		$res = sql_query($query);
		return mysql_fetch_array($res);
	}

	function removeLogevent($id) {
		$query = ' delete from '.$this->table_log.' where id = '.$id;
		$res = sql_query($query);
		return $res;
	}
	function countreftable() {
		$query = 'select count(*) as total from '.$this->table_ref.' where ref >= 1000000';
		$res = sql_query($query);
		$arr = mysql_fetch_array($res);
		return $arr['total'];
	}

} // class NaiveBayesianStorage

