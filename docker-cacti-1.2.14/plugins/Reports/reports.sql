
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` int(10) NOT NULL auto_increment,
  `name` varchar(100) NOT NULL default '',
  `hour` int(2) NOT NULL default '0',
  `minute` int(2) NOT NULL default '0',
  `email` text NOT NULL,
  `rtype` varchar(12) NOT NULL default 'attach',
  `lastsent` int(32) NOT NULL default '0',
  `daytype` text NOT NULL,
  KEY `id` (`id`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `reports_data`;
CREATE TABLE `reports_data` (
  `id` int(10) NOT NULL auto_increment,
  `reportid` int(10) NOT NULL default '0',
  `hostid` int(10) NOT NULL default '0',
  `local_graph_id` int(10) NOT NULL default '0',
  `rra_id` int(10) NOT NULL default '0',
  `type` int(1) NOT NULL default '0',
  `item` varchar(32) NOT NULL default '',
  `data` text NOT NULL,
  `gorder` int(5) NOT NULL default '0',
  KEY `id` (`id`)
) ENGINE=MyISAM;