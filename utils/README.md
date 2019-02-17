Running one of the scripts
---------------------------

Scripts in this folder are ment to be called on the command line by
either executing one of the shell wrappers `LetoDMS-*` or by calling
`php -f <scriptname> -- <script options>`. Most scripts have an option
`-h` or `--help` which list the available script options.

Be aware that this scripts are not officially supported. Use them with
care and always ensure to have a backup of your data before running
any of them.

Do not allow regular users to run this scripts!
-----------------------------------------------

None of the scripts do any authentication. They all run with a LetoDMS
admin account! So anybody being allowed to run the scripts can modify
your DMS content.

Adding documents
------------------

Single documents can be added with `LetoDMS-adddoc`. The script is just for
adding new documents but not for adding a new version. As the script is
just a small wrapper around the controller for adding documents by the web gui,
it will also trigger all hooks, but it will not send any notification to the
users.

If you run `LetoDMS-adddoc` make sure to run it with the permissions
of the user running your web server. It will copy files right into
the content directory of your LetoDMS installation. Don't do this
as root because you will most likely not be able to remove those documents
via the web gui. If this happens by accident, you will still be able
to fix it manually by setting the propper file permissions for the document
just created in your content directory. Just change the owner of the
document folder and its content to the user running the web server.

Indexing for fulltext search
-----------------------------

Instead of regulary updating the full text index from the web gui, you
can as well run `LetoDMS-indexer` in a cron job. It will either update
or recreate the full text index.
