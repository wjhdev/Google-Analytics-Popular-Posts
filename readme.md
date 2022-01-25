# Google Analytics Popular Posts

**_Warning:_** this plugin is under active development. Updating to new versions might require reactivating to rebuild database tables, as there is currently no upgrading framework.

## Documentation

- [Index](docs/index.md)
- [Setup](docs/setup.md)

## Questions

###### _What data are you capturing?_

We're currently calling and caching the `ga:pageviews` to the database for each `ga:pagepath` dimension. Values for the current day and previous are stored during each cron job. A post id is resolved for each `ga:pagepath` that corresponds to an actual post.

###### _How do I query the data myself?_

The data is stored across two database tables. The first (`bt_analyticsbridge_pages`) stores each `ga:pagepath` with a unique id and corresponding post id (if it exists).

The second table (`bt_analyticsbridge_metrics`) relates a `page_id` to a metric & value over a start & end date.

To query this data yourself, find the corresponding page_id from the pages table and select using it from the metrics table. This can be accomplished using joins.
