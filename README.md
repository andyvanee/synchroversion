Synchroversion - File-based, versioning cache system.
===================================================

Synchroversion is designed with the following properties in mind:

- Keep full history of changes (diffs)
- Writing does not block or interfere with reading
- Reading does not block or interfere with writing
- Changes are timestamped

In general, it is built around the assumption that your data is:

- Text-based
- Not changing wildly on every call (so diffs aren't huge)
- Line-oriented (if you want readable diffs)
- Single producer, multiple consumer
- Fetch speed faster than fetch frequency. I would expect that it may blow
  up if your fetch takes 5 minutes but you run it every 2 minutes.

Some possible use cases might be:

- Storing and versioning a large API call
- Recording changes made to a directory tree
- Adding database versioning (using database dumps)

It does this by storing a log of changes to the content in diff format in
a `state` directory, and the latest few full versions of the file in a `latest`
directory. The producer and consumer are never accessing the same file at the
same time.

My initial prototype was written in bash, but this version is in PHP since it's
what I'm currently working in. Since the format is completely text file based,
it is language agnostic and should be quite simple to implement in other
languages as well.

### producer.php

```php
<?php
$sync = new Synchroversion\Synchroversion(dirname(__FILE__), 'syslog');
$sync->exec(function () {
    return file_get_contents('/var/log/system.log');
});
```

You initialize it with the directory for the "root" as well as a name for your
repository. The exec method takes a callback that should return whatever you
want to store. Every time you run this producer, it will fetch the content and
write a `state` file and a `latest` file if anything has changed.

The producer will likely be run regularly via cron or something like that. It
could run anywhere from every minute to once a year - pick what makes sense for
your data.

### consumer.php

```php
<?php
$sync = new Synchroversion\Synchroversion(dirname(__FILE__), 'syslog');
echo $sync->latest();
```

The consumer is initialized in the same way, but only calls the `latest` method
to fetch the latest version of the file.

```bash
# Run the update every 5 seconds
while [[ true ]]; do php producer.php; sleep 5; done

# In another terminal, fetch the latest version
php consumer.php
```

## Todo

- Playback state files to validate current state
- Playback state files to generate historical states
- Compressed state files
