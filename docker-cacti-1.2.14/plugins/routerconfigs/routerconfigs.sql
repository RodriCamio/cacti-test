
CREATE TABLE plugin_routerconfigs_accounts (
  id int(12) NOT NULL auto_increment,
  `name` varchar(64) NOT NULL,
  username varchar(64) NOT NULL,
  `password` varchar(256) NOT NULL,
  `enablepw` varchar(256) NOT NULL,
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

CREATE TABLE plugin_routerconfigs_backups (
  id int(12) NOT NULL auto_increment,
  btime int(18) NOT NULL,
  device int(12) NOT NULL,
  `directory` varchar(255) NOT NULL,
  filename varchar(255) NOT NULL,
  config longtext NOT NULL,
  lastchange int(24) NOT NULL,
  username varchar(128) NOT NULL,
  PRIMARY KEY  (id),
  KEY btime (btime),
  KEY device (device),
  KEY `directory` (`directory`),
  KEY lastchange (lastchange)
) ENGINE=MyISAM;

CREATE TABLE plugin_routerconfigs_devices (
  id int(12) NOT NULL auto_increment,
  enabled varchar(2) NOT NULL default 'on',
  ipaddress varchar(128) NOT NULL,
  hostname varchar(255) NOT NULL,
  `directory` varchar(255) NOT NULL,
  account int(12) NOT NULL,
  lastchange int(24) NOT NULL,
  username varchar(128) NOT NULL,
  schedule int(6) NOT NULL default 1,
  lasterror varchar(255) NOT NULL,
  lastbackup int(18) NOT NULL,
  lastattempt int(18) NOT NULL,
  devicetype int(12) NOT NULL,
  debug text NOT NULL,
  PRIMARY KEY  (id),
  KEY enabled (enabled),
  KEY schedule (schedule),
  KEY ipaddress (ipaddress),
  KEY account (account),
  KEY lastbackup (lastbackup),
  KEY lastchange (lastchange),
  KEY lastattempt (lastattempt),
  KEY devicetype (devicetype)
) ENGINE=MyISAM;

CREATE TABLE plugin_routerconfigs_devicetypes (
  id int(12) NOT NULL auto_increment,
  `name` varchar(128) NOT NULL,
  username varchar(64) NOT NULL default 'sername:',
  `password` varchar(128) NOT NULL default 'assword:',
  copytftp varchar(64) NOT NULL default 'copy tftp run',
  version varchar(64) NOT NULL default 'show version',
  confirm varchar(64) NOT NULL default '',
  forceconfirm int(1) NOT NULL,
  PRIMARY KEY  (id)
) ENGINE=MyISAM;

INSERT INTO plugin_routerconfigs_devicetypes (id, name, username, password, copytftp, version, confirm, forceconfirm) VALUES (1, 'Cisco IOS', 'sername:', 'assword:', 'copy run tftp', 'show version', 'y', 0),
(2, 'Cisco CatOS', 'sername:', 'assword:', 'copy config tftp', '', 'y', 1);




