JayPS Search
======

A very simple search engine for Novius OS, based on Behaviours.

Licensed under [MIT License](http://opensource.org/licenses/MIT)

Current version: 0.1

**Get started**

* Execute the MySQL script jayps_search/create_tables.sql in your Novius OS database.
* Add a behaviour 'JayPS\Search\Orm_Behaviour_Searchabl' in one of your model.

**Example with Nos\Monkey**
[Nos\Monkey](https://github.com/novius-os/noviusos_monkey)

Just add the behaviour 'JayPS\Search\Orm_Behaviour_Searchable' in the model definition:

    protected static $_behaviours = array(

        (...)

        'JayPS\Search\Orm_Behaviour_Searchable' => array(
            'events' => array('after_save'),
            'fields' => array('monk_name', 'monk_summary'),
        ),
    );