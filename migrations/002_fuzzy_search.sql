ALTER TABLE `jayps_search_word_occurence` ADD `mooc_word_hash` VARCHAR( 255 ) NULL DEFAULT NULL , ADD INDEX ( `mooc_word_hash` );
