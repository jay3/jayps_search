<?php

namespace JayPS\Search;

class Model_Keyword extends \Nos\Orm\Model
{
    protected static $_table_name = 'jayps_search_word_occurence';
    protected static $_primary_key = array('mooc_word');
}
