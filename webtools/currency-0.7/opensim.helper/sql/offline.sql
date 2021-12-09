-- --------------------------------------------------------
-- for Offline Message
-- --------------------------------------------------------
    
CREATE TABLE `offline_message` (
    `to_uuid`       varchar(36) NOT NULL,
    `from_uuid`     varchar(36) NOT NULL,
    `message`       text NOT NULL,
    KEY `to_uuid` (`to_uuid`)
) TYPE=MyISAM;
    
