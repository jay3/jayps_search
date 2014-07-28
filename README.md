JayPS Search
======

A very simple search engine for Novius OS, based on Behaviours.

Licensed under [MIT License](http://opensource.org/licenses/MIT)

Current version: 1.2

**Get started**

* Configure which models will be searchable in your bootstrap.php (see below).
 For examples, see [JayPS Search Test](https://github.com/jay3/jayps_search_test)
* Add new items or save existing ones
* Start searching using find('all', array('where' => array(array('keywords', 'keyword1 keyword2'))))

**Example with Nos\Page and Nos\Monkey**
[Nos\Monkey](https://github.com/novius-os/noviusos_monkey)

Configure which models will be searchable for example in your bootstrap.php:
```php
    \Event::register_function('config|jayps_search::config', function(&$config) {
        $config['observed_models']['noviusos_monkey::model/monkey'] = array(
            'primary_key' => 'monk_id',
            'config_behaviour' => array(
                'fields' => array('monk_name', 'monk_summary', 'wysiwygs->content'),
            ),
        );

        $config['observed_models']['noviusos_page::model/page'] = array(
            'primary_key' => 'page_id',
            'config_behaviour' => array(
                'fields' => array('page_title', 'wysiwygs->content', 'page_meta_title', 'page_meta_description', 'page_meta_keywords'),
            ),
        );

        $config['observed_models']['noviusos_news::model/post'] = array(
            'primary_key' => 'post_id',
            'config_behaviour' => array(
                'fields' => array('post_title', 'wysiwygs->content'),
            ),
        );
    });

    \JayPS\Search\Orm_Behaviour_Searchable::init();
    \Event::register_function('front.start', function() {
        // add models you want to use in your search and that are used in your template before your search enhancer
        // for example: noviusos_page::model/page

        //\JayPS\Search\Orm_Behaviour_Searchable::init_relations('noviusos_page::model/page');
        //\JayPS\Search\Orm_Behaviour_Searchable::init_relations('noviusos_news::model/post');

        \JayPS\Search\Orm_Behaviour_Searchable::init_relations();
    });
```

The config of the behaviour and the app can define score improvement as follow :

```php
    $config['observed_models']['noviusos_monkey::model/monkey'] = array(
        'primary_key' => 'monk_id',
        'config_behaviour' => array(
            'fields' => array(
                'monk_name', // as this is the title, general config already defines a boost
                'monk_summary' => 1, //
                'wysiwygs->content' => array(
                    'boost' => 0, // changes nothing, default value is 0
                    'is_html' => true // will remove html markup.
                )
            ),
        ),
    );
    $config['observed_models']['noviusos_news::model/post'] = array(
        'primary_key' => 'post_id',
        'config_behaviour' => array(
            'fields' => array(
                'post_title' => 2, // override general boost
                'wysiwygs->content' => array(
                    'boost' => 1 // gives a score for all words in the content, regardless of their nature
                    'is_html' => array(
                        'h1' => 1 // provides another boost for words un h1 markup
                    )
                )
            ),
        ),
    );
```

To use the search with find(), simply provide an array of keywords. '*' acts as a joker at the end.
```php
    \JayPS\Search\Orm_Behaviour_Searchable::init_relations();

    $pages = \Nos\Page\Model_Page::find('all', array(
        'where' => array(
            array('keywords', 'chimpa* monkey'),
            'page_published' => 1,
            'page_context'   => \Nos\Nos::main_controller()->getPage()->page_context,
        ),
        'rows_limit' => 10,
        'order_by' => array('jayps_search_score', 'page_title'),
    ));

    $news = \Nos\BlogNews\News\Model_Post::find('all', array(
        'where' => array(
            array('keywords', 'chimpa* monkey'),
            'post_published' => 1,
            'post_context'   => \Nos\Nos::main_controller()->getPage()->page_context,
        ),
        'rows_limit' => 10,
        'order_by'   => array('jayps_search_score', 'post_title'),
    ));

    $monkeys = \Nos\Monkey\Model_Monkey::find('all', array(
        'where' => array(
            array('keywords', 'chimpa* monkey'),
            'monk_published' => 1,
            'monk_context'   => \Nos\Nos::main_controller()->getPage()->page_context,
        ),
        'rows_limit' => 200,
        'order_by' => array('jayps_search_score', 'monk_name'),
    ));
```
You can also restrict search to specific fields:
```php
    $pages = \Nos\Page\Model_Page::find('all', array(
        'where' => array(
            'keywords_fields' => array(
                'page_title'                    => 'keyword1 keyword2', // keyword1 and keyword2 must appear in title
                'wysiwygs->content'             => 'keyword3',          // keyword3 must appear in description
                '*'                             => 'keyword4',          // keyword4 must appear anywhere
                'page_title|wysiwygs->content'  => 'keyword5',          // keyword5 must appear in title OR description
            ),
        ),
    ));
```