--
-- Table structure for table `data_source_stats_daily`
--

CREATE TABLE `data_source_stats_daily` (
  `local_data_id` mediumint(8) unsigned NOT NULL auto_increment,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE NOT NULL,
  `peak` DOUBLE NOT NULL,
  PRIMARY KEY  (`local_data_id`,`rrd_name`)
) ENGINE=MyISAM DEFAULT CHARSET=UTF8;

--
-- Table structure for table `data_source_stats_hourly`
--

CREATE TABLE `data_source_stats_hourly` (
  `local_data_id` mediumint(8) unsigned NOT NULL auto_increment,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE NOT NULL,
  `peak` DOUBLE NOT NULL,
  PRIMARY KEY  (`local_data_id`,`rrd_name`)
) ENGINE=MyISAM DEFAULT CHARSET=UTF8;

--
-- Table structure for table `data_source_stats_hourly_cache`
--

CREATE TABLE `data_source_stats_hourly_cache` (
  `local_data_id` mediumint(8) unsigned NOT NULL auto_increment,
  `rrd_name` varchar(19) NOT NULL,
  `time` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `value` DOUBLE NOT NULL,
  PRIMARY KEY  (`local_data_id`,`time`,`rrd_name`),
  KEY `time` (`time`)
) ENGINE=MEMORY DEFAULT CHARSET=UTF8;

--
-- Table structure for table `data_source_stats_hourly_last`
--

CREATE TABLE `data_source_stats_hourly_last` (
  `local_data_id` mediumint(8) unsigned NOT NULL auto_increment,
  `rrd_name` varchar(19) NOT NULL,
  `value` varchar(30) NOT NULL,
  `calculated` DOUBLE NOT NULL,
  PRIMARY KEY  (`local_data_id`,`rrd_name`)
) ENGINE=MEMORY DEFAULT CHARSET=UTF8;

--
-- Table structure for table `data_source_stats_monthly`
--

CREATE TABLE `data_source_stats_monthly` (
  `local_data_id` mediumint(8) unsigned NOT NULL auto_increment,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE NOT NULL,
  `peak` DOUBLE NOT NULL,
  PRIMARY KEY  (`local_data_id`,`rrd_name`)
) ENGINE=MyISAM DEFAULT CHARSET=UTF8;

--
-- Table structure for table `data_source_stats_weekly`
--

CREATE TABLE `data_source_stats_weekly` (
  `local_data_id` mediumint(8) unsigned NOT NULL auto_increment,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE NOT NULL,
  `peak` DOUBLE NOT NULL,
  PRIMARY KEY  (`local_data_id`,`rrd_name`)
) ENGINE=MyISAM DEFAULT CHARSET=UTF8;

--
-- Table structure for table `data_source_stats_yearly`
--

CREATE TABLE `data_source_stats_yearly` (
  `local_data_id` mediumint(8) unsigned NOT NULL auto_increment,
  `rrd_name` varchar(19) NOT NULL,
  `average` DOUBLE NOT NULL,
  `peak` DOUBLE NOT NULL,
  PRIMARY KEY  (`local_data_id`,`rrd_name`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=UTF8;
