<?php

return array(
    /** @brief Table de liaison avec les mots clés */
    'table_liaison' => 'jayps_search_word_occurence',

    /** @brief Préfixe de la table de liaison avec les mots clés */
    'table_liaison_prefixe' => 'mooc_',

    /** @brief mots clés interdits */
    'forbidden_words' => array(
        // 3 lettres
        'les', 'des', 'ses', 'son', 'mes', 'mon', 'tes', 'ton', 'une', 'aux', 'est', 'sur', 'par', 'dit',
        'the',
        // 4 lettres
        'pour','sans','dans','avec','deux','vers',
        // 5 lettres
        'titre',
    ),

    /** @brief longueur mimimum des mots à indexer */
    'min_word_len' => 3,

    /** @brief max number of joins for a search */
    'max_join' => 4,

    /** @brief For debugging */
    'debug' => false,

    /** @brief use a transaction to speed up InnoDB insert */
    'transaction' => false,

    /** @brief use INSERT DELAYED, for MyISAM Engine only*/
    'insert_delayed' => true,

    /** @brief group insertion of words */
    'words_by_insert' => 100,

    /** @brief score can be improved by this config*/
    /* uncomment to use default boost
    'title_boost' => 1,

    'html_boost' => array(
        'h1' => 1,
    )*/
);
