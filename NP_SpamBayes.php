<?php
/**
 * NP_SpamBayes
 * by Xiffy. http://xiffy.nl/weblog/
 *
 * Bayesian filter for comment and trackback spam
 *

   The Initial Developer of the Original Code is
   Loic d'Anterroches [loic_at_xhtml.net].
   Portions created by the Initial Developer are Copyright (C) 2003
   the Initial Developer. All Rights Reserved.

   Contributor(s):

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

 * based on: many sources:
 * http://priyadi.net/archives/2005/10/07/wpbayes-naive-bayesian-comment-spam-filter-for-wordpress/
 * http://www.xhtml.net/php/PHPNaiveBayesianFilter
 * http://www.opensourcetutorials.com/tutorials/Server-Side-Coding/PHP/implement-bayesian-inference-using-php-1/page11.html
 * http://weblogtoolscollection.com/archives/2005/02/19/three-strikes-spam-plugin-updated/
 * http://www-128.ibm.com/developerworks/web/library/wa-bayes1/?ca=dgr-lnxw961Bayesian
 */

if (!function_exists('sql_table')) {
       function sql_table($name) {    return 'nucleus_' . $name; }
}
if (! function_exists(redirect) ) {
    function redirect($url) {
        $url = preg_replace('|[^a-z0-9-~+_.?#=&;,/:@%]|i', '', $url);
        header('Location: ' . $url);
        exit;
    }
}
class NP_SpamBayes extends NucleusPlugin {

    function NP_SpamBayes() {
        global $DIR_PLUGINS;
        $this->table_cat = sql_table('plug_sb_cat'); // categories
        $this->table_wf  = sql_table('plug_sb_wf');  // word frequencies
        $this->table_ref = sql_table('plug_sb_ref'); // references
        $this->table_log = sql_table('plug_sb_log'); // logging
        include_once($DIR_PLUGINS."spambayes/spambayes.php");
        $this->spambayes = new NaiveBayesian(&$this);
    }

    /* some default functions for a plugin */
    function getName()        { return 'SpamBayes'; }
    function getAuthor()      { return 'xiffy & Lord Matt'; }
    function getURL()         { return 'http://wakka.xiffy.nl/spambayes'; }
    function getVersion()     { return '1.2.0b'; }
    function getDescription() { return 'SpamBayes filter for comment and trackback spam. In adherence with Spam API 1.0 for Nucleus';    }
    function supportsFeature($what) {
        switch($what) {
            case 'SqlTablePrefix':
                return 1;
                    case 'HelpPage':
                        return 1;
            default:
                return 0;
        }
    }
    function getEventList() {
        return array('QuickMenu','PreAddComment','PreActionAddComment','ValidateForm', 'SpamCheck');
    }

    function hasAdminArea() {
        return 1;
    }

    /* Where the action is! */
    function event_PreAddComment(&$data) {
        global $DIR_PLUGINS;
        $comment = $data['comment'];
        $score = $this->spambayes->categorize($comment['body'].' '.$comment['host'].' '.$comment['user'].' '.$comment['userid'].' '.$comment['ip']);
        $log = $this->logevent > '' ? $this->logevent : 'event PreAddComment';
        $this->logevent = '';
        if ((float)$score['spam'] > (float)$this->getOption('probability')) {
            $this->spambayes->nbs->logevent(
                    $log.' SPAM detected. score: (ham '.$score['ham'].') (spam: '.$score['spam'].') itemid:'.$comment['itemid'],
                    $comment['body'].'^^'.$comment['host'].'^^'.$comment['user'].'^^'.$comment['userid'].'^^'.$comment['ip'],
                    'spam'
            );
            redirect($this->getOption('redirect'));
            exit;
        } else {
            $this->spambayes->nbs->logevent(
                    $log.' Accepting HAM score: (ham '.$score['ham'].') (spam: '.$score['spam'].')',
                    $comment['body'].' '.$comment['host'].' '.$comment['user'].' '.$comment['userid'].' '.$comment['ip'],
                    'ham'
            );
        }
    }

    function event_ValidateForm(&$data) {
        $this->logevent = 'event ValidateForm';
        $this->event_PreAddComment(&$data);
    }

    function event_SpamCheck (&$data) {
        global $DIR_PLUGINS;
        // maybe some other plugin got this already
        if (isset($data['spamcheck']['result']) &&
                  $data['spamcheck']['result'] == true) {
            // Already checked...the caller wants this back and is spam. we're off!
            return;
        }
        $score = $this->spambayes->categorize($data['spamcheck']['data']);

        if ((float)$score['spam'] > (float)$this->getOption('probability')) {
            $log = $data['spamcheck']['type'] > '' ? $data['spamcheck']['type'] ." SpamCheck":"event SpamCheck";
            $this->spambayes->nbs->logevent(
                    $log.' SPAM detected. score: (ham '.$score['ham'].') (spam: '.$score['spam'].')',
                    $data['spamcheck']['data'],
                    'spam'
                    );
            if  (isset($data['spamcheck']['return']) && $data['spamcheck']['return'] == true) {
                // Return to caller
                $data['spamcheck']['type'] .= ' (SpamBayes)';
                $data['spamcheck']['result'] = true;
                return;
            } else {
                redirect($this->getOption('redirect'));
            }
        } elseif ( ($data['spamcheck']['type'] == 'MailtoaFriend' && trim($data['spamcheck']['data']) <> '') ||
                   ($data['spamcheck']['type'] == 'referrer2'     && trim($data['spamcheck']['data']) <> '') ||
                   ($data['spamcheck']['type'] == 'trackback'     && trim($data['spamcheck']['data']) <> '') ) {
            $log = $data['spamcheck']['type'] > '' ? $data['spamcheck']['type'] ." SpamCheck":"event SpamCheck";
            $this->spambayes->nbs->logevent(
                    $log.' HAM detected. score: (ham '.$score['ham'].') (spam: '.$score['spam'].')',
                    $data['spamcheck']['data'],
                    'ham'
                    );
        }
            // in case of SpamCheck we do NOT log HAM events ...
    }

    function event_QuickMenu(&$data) {
        global $member, $nucleus, $blogid;
        // only show to admins
        if (preg_match("/MD$/", $nucleus['version'])) {
            $isblogadmin = $member->isBlogAdmin(-1);
        } else {
            $isblogadmin = $member->isBlogAdmin($blogid);
        }
        if (!($member->isLoggedIn() && ($member->isAdmin() | $isblogadmin))) return;
        if ($this->getOption('enableQuickmenu') == 'yes' ) {
            array_push(
                $data['options'],
                array(
                    'title' => 'SpamBayes',
                    'url' => $this->getAdminURL(),
                    'tooltip' => 'Manage SpamBayes filter'
                )
            );
        }
    }

    function install() {
        // create some options
        $this->createOption('probability','Score at which point we sould consider a text as spam?','text','0.95');
        $this->createOption('redirect','To which URL should spammers be redireted?','text','http://127.0.0.1/');
        $this->createOption('ignorelist','Which words should not be taken into consideration?','textarea','you the for and');
        $this->createOption('enableTrainall','Show SpamBayes train all ham in menu?','yesno','yes');
        $this->createOption('enableQuickmenu','Show SpamBayes in quickmenu?','yesno','no');
        $this->createOption('enableLogging','Use SpamBayes action logging? (this could slow down during a spamrun and can cost huge amounts of db space!)','yesno','no');

        // create some sql tables as well
        sql_query("CREATE TABLE IF NOT EXISTS ".$this->table_cat." (catcode varchar(250) NOT NULL default '',  probability double NOT NULL default '0', wordcount bigint(20) NOT NULL default '0',  PRIMARY KEY (catcode))");
        sql_query("CREATE TABLE IF NOT EXISTS ".$this->table_wf." (word varchar(250) NOT NULL default '', catcode varchar(250) NOT NULL default '', wordcount bigint(20) NOT NULL default '0',  PRIMARY KEY (word, catcode))");
        sql_query("CREATE TABLE IF NOT EXISTS ".$this->table_ref." (ref bigint(20) NOT NULL, catcode varchar(250) NOT NULL default '', content text NOT NULL default '',  PRIMARY KEY (ref), KEY(catcode))");
        sql_query("CREATE TABLE IF NOT EXISTS ".$this->table_log." (id bigint(20) NOT NULL auto_increment, log varchar(250) NOT NULL default '', content text NOT NULL default '',  catcode varchar(250) NOT NULL default '', logtime timestamp, PRIMARY KEY (id), KEY(catcode))");
        // create 'ham' and 'spam' categories
        sql_query("insert into ".$this->table_cat." (catcode) values ('ham')");
        sql_query("insert into ".$this->table_cat." (catcode) values ('spam')");
    }

    function unInstall() {
        // uncomment the next three lines if you want to drop the filter tables
        // sql_query('drop table if exists '.$this->table_cat);
        // sql_query('drop table if exists '.$this->table_ref);
        // sql_query('drop table if exists '.$this->table_wf);
        // sql_query('drop table if exists '.$this->table_log);
    }

}
