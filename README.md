Index-Only SQL library (IOSQL)
=====

What if you only ever asked the database to do calculations and you used memcached/redis for actual result retrieval?

In this model MySQL/PostgreSQL or SQlite is the master for the data storage. However, it is only a *fallback* for reads as the application uses memcached (or redis) for fetching objects most the time.

The one weakness is that your application must now enforce a type of ACID complient since your database is no longer the sole holder of data.
