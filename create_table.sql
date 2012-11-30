CREATE TABLE IF NOT EXISTS `jayps_search_word_occurence` (
  `mooc_word` varchar(255) NOT NULL,
  `mooc_join_table` varchar(255) NOT NULL,
  `mooc_foreign_id` int(11) NOT NULL,
  `mooc_field` varchar(255) NOT NULL,
  `mooc_ordre` smallint(5) unsigned NOT NULL,
  KEY `mooc_mot` (`mooc_word`,`mooc_join_table`,`mooc_foreign_id`),
  KEY `mooc_type` (`mooc_field`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

