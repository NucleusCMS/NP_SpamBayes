NP_SpamBayes
============

This is an import of NP_SpamBayes 1.1.0 with a minor collection of changes and then updated to address bugs and any feature wishes. The aim here is to polish xiffy's already fine work and add a help file.

History
-------------------------
* Version 1.0   : 2006 09 06 Stable on development and fresh installed blog.
* Version 1.0.1 : 2006 09 11 NAN bug solved, some more information on the screens
* Version 1.0.2 : 2006 09 15 Logging filtering applied to both ham and spam as well as different logtypes. Handy when
             a lot of plugins use spambaues as a spam filter.
* Version 1.0.3 : 2006 09 19 Logging now adherse the plugin option setting (thanks VJ)
             Added the feature to train all 'new' comments
* Version 1.0.4 : 2006 09 26 Logging now adherse the plugin option setting also in version 4 of PHP (thanks pepiino)
* Version 1.0.5 : 2006 10 15 Update probabilities now made obsolete. The function is run after all training sessions.
* Version 1.1.0 Beta 2007 01 07 Logger functions have been enhanched dramaticly.
             Items per page is now a user setting.
             It's possible to scan for keywords inside the content
             Explain functionality to see how a logged event scores against SpamBayes keywords. Prints both ham and spam results.
* Version 1.1.0    2007 01 08      Promote to weblog. Comments only. Will teach the document a s Ham and publishes the logged event as a legit comment.
             Pagecounter could be wrong..
