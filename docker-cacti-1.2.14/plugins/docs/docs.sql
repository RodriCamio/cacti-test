CREATE TABLE `plugin_docs` (
   `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) default '',
  `data` BLOB,
  `updated` int(11) NOT NULL default '0',
  `creator` varchar(255) NOT NULL default '',
  `updatedby` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;