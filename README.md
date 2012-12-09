JayPS Search
======

A very simple search engine for Novius OS, based on Behaviours.

Licensed under [MIT License](http://opensource.org/licenses/MIT)

Current version: 0.2

**Get started**

* Execute the MySQL script jayps_search/create_tables.sql in your Novius OS database.
* Add the behaviour 'JayPS\Search\Orm_Behaviour_Searchabl' in one of your model.
* Add new items or save existing ones
* Start searching using find('all', array('where' => array(array('keywords', array('keyword1', 'keyword2')))))

**Example with Nos\Monkey**
[Nos\Monkey](https://github.com/novius-os/noviusos_monkey)

Just add the behaviour 'JayPS\Search\Orm_Behaviour_Searchable' in the model definition.
And if you want to use the ORM with find(), make $_has_many public:

    public static $_has_many;

    protected static $_behaviours = array(

        (...)

        'JayPS\Search\Orm_Behaviour_Searchable' => array(
            'fields' => array('monk_name', 'monk_summary'),
        ),
    );

To use the search with find(), simply provide an array of keywords. '*' acts as a joker at the end.

    $monkeys = \Nos\Monkey\Model_Monkey::find('all', array(
        'where' => array(
            array('keywords', array('monkey', 'chimpanz*')),
        ),
        'order_by' => array('monk_name' => 'asc')
    ));
