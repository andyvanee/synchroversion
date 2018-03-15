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

## Usage

Install with composer:

```bash
composer require andyvanee/synchroversion
```

### new Synchroversion\Synchroversion($root, $path)

Create a new instance, ready for reading or writing. The first argument is the
root directory where all files are stored and the second is the path within
that root you'd like to use. We'll be tracking syslog in this example, so we'll
just name it `syslog`.

```php
$sync = new Synchroversion\Synchroversion(dirname(__FILE__), 'syslog');
```

### setUmask($umask)

This can be used to set the permissions of files and directories created. The
default umask is `0022`, which means world-readable and user-writeable. You may
choose to set this to `0000` if you want the permissions wide open, or `0007`
if you want read/write for the user and group but deny permissions to others.
Check out documentation for UNIX `umask` for more examples.

```php
$sync->setUmask(0007);
```

### latest()

This returns the latest version of the content that has been stored.

```php
$content = $sync->latest();
```

### exec(callable $cb)

To write a version of the content, pass a callable which returns the data
to be stored. A version file will be created as well as a diff if there
are previous versions.

```php
$sync->exec(function(){
    return 'Hello World!';
});
```

### retainState(int $count)

By default, the three latest full versions of the content are kept. This may
be adjusted by setting this to less or more. Note that diffs are kept for the
entire history, so it is always possible to reconstruct any past state. These
are simply kept to review consistency.

```php
// Hoard all the versions!
$sync->retainState(10);

// Save my disk space!
$sync->retainState(1);
```

State files are cleaned up on every call to exec(). If you'd like to clean up
state files without calling exec, call `purgeStateFiles()` instead.

```php
$sync->retainState(1);
$sync->purgeStateFiles()
```

## Example files

```php
<?php
// producer.php
require 'vendor/autoload.php';

$sync = new Synchroversion\Synchroversion(dirname(__FILE__), 'syslog');

$sync->exec(function () {
    return file_get_contents('/var/log/system.log');
});
```

```php
<?php
// consumer.php
require 'vendor/autoload.php';

$sync = new Synchroversion\Synchroversion(dirname(__FILE__), 'syslog');

echo $sync->latest();
```

These two files illustrate a writer and a reader which track the syslog file.

A producer would likely be run regularly via cron or triggered by some other
action. It could run anywhere from every minute to once a year - pick what
makes sense for your data.

Run the update every 5 seconds:

```bash
while [[ true ]]; do php producer.php; sleep 5; done
```

In another terminal, fetch the latest version:

```bash
php consumer.php
```

## Todo

- Playback state files to validate current state
- Playback state files to generate historical states
- Compressed state files
