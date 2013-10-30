CREATE TABLE IF NOT EXISTS `jayps_search_word_occurence` (
  `mooc_word` varchar(255) NOT NULL,
  `mooc_join_table` varchar(255) NOT NULL,
  `mooc_foreign_id` int(11) NOT NULL,
  `mooc_field` varchar(255) NOT NULL,
  `mooc_score` tinyint(3) unsigned NOT NULL,
  KEY `mooc_word` (`mooc_word`),
  KEY `mooc_join_table` (`mooc_join_table`),
  KEY `mooc_foreign_id` (`mooc_foreign_id`),
  KEY `mooc_field` (`mooc_field`)
) DEFAULT CHARSET=utf8;
