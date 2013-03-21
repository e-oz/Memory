<?php
/*
 * The content of this file is a description of the Couchbase API, so that you
 * may configure your IDE for code completion, documentation and constants.
 */

////////////////////////////////////////////////////////
//                                                    //
//            The following error codes exist         //
//                                                    //
////////////////////////////////////////////////////////

/**
 * Everything is OK.
 */
        const COUCHBASE_SUCCESS = LCB_SUCCESS;
/**
 * This is an internal error message.
 */
        const COUCHBASE_AUTH_CONTINUE = LCB_AUTH_CONTINUE;
/**
 * Increment/decrement on an object that isn't a number.
 */
        const COUCHBASE_DELTA_BADVAL = LCB_DELTA_BADVAL;
/**
 * The object is too big to be stored on the server.
 */
        const COUCHBASE_E2BIG = LCB_E2BIG;
/**
 * The server is too busy to handle your request. Please try again later.
 */
        const COUCHBASE_EBUSY = LCB_EBUSY;
/**
 * An internal error in the Couchbase extension.
 * You should probably submit a bug report for this.
 */
        const COUCHBASE_EINTERNAL = LCB_EINTERNAL;
/**
 * Out of resources.
 */
        const COUCHBASE_ENOMEM = LCB_ENOMEM;
/**
 * Generic error code.
 */
        const COUCHBASE_ERROR = LCB_ERROR;
/**
 * Temporarily cannot handle request. A later retry may succeed.
 */
        const COUCHBASE_ETMPFAIL = LCB_ETMPFAIL;
/**
 * The key exists, but the CAS identifier provided did not match the one for
 * the object in the cluster.
 */
        const COUCHBASE_KEY_EEXISTS = LCB_KEY_EEXISTS;
/**
 * The key does not exist.
 */
        const COUCHBASE_KEY_ENOENT = LCB_KEY_ENOENT;
/**
 * An error occurred while trying to read/write data to the network.
 */
        const COUCHBASE_NETWORK_ERROR = LCB_NETWORK_ERROR;
/**
 * The command was sent to the wrong server. This problem may occur if
 * someone added/removed a node to the cluster. Retrying the operation may
 * solve the problem.
 */
        const COUCHBASE_NOT_MY_VBUCKET = LCB_NOT_MY_VBUCKET;
/**
 * The document was not stored.
 */
        const COUCHBASE_NOT_STORED = LCB_NOT_STORED;
/**
 * The server knows about this command, but the datastore doesn't support it
 * for some reason.
 */
        const COUCHBASE_NOT_SUPPORTED = LCB_NOT_SUPPORTED;
/**
 * The server did not understand the command we sent. This may occur if you
 * are attempting to use an operation not supported on an older version of
 * Couchbase Server.
 */
        const COUCHBASE_UNKNOWN_COMMAND = LCB_UNKNOWN_COMMAND;
/**
 * Failed to lookup the host.
 */
        const COUCHBASE_UNKNOWN_HOST = LCB_UNKNOWN_HOST;


////////////////////////////////////////////////////////
//                                                    //
//        The following option constants exist        //
//                                                    //
////////////////////////////////////////////////////////

/**
 * Specifies the serializer to use to store objects in the cluster.
 */
        const COUCHBASE_OPT_SERIALIZER = 1;
/**
 * Specifies the compression to use for big objects.
 */
        const COUCHBASE_OPT_COMPRESSION = 2;
/**
 * A text string used as a prefix for all keys (may be used to create your
 * own namespace).
 */
        const COUCHBASE_OPT_PREFIX_KEY = 3;
/**
 * This option is used to disable the deserialisation of the
 * data received from the cluster. It is mainly used by the
 * developers of the extension to be able to test variable
 * parts of the API and should not be used by end users
 * (it may be removed without notice if we find a better way to do
 * this).
 */
        const COUCHBASE_OPT_IGNOREFLAGS = 4;
/**
 * @todo figure out what this is used for...
 */
        const COUCHBASE_OPT_VOPTS_PASSTHROUGH = 5;

/**
 * Constant representing the PHP serializer.
 */
        const COUCHBASE_SERIALIZER_PHP = 0;
/**
 * Constant representing the JSON serializer.
 */
        const COUCHBASE_SERIALIZER_JSON = 1;
/**
 * Constant representing the JSON serializer, but deserialise into arrays.
 */
        const COUCHBASE_SERIALIZER_JSON_ARRAY = 2;
/**
 * Constant representing no compression.
 */
        const COUCHBASE_COMPRESSION_NONE = 0;
/**
 * Constant representing zlib compression.
 */
        const COUCHBASE_COMPRESSION_ZLIB = 1;
/**
 * Constant representing fastlz compression.
 */
        const COUCHBASE_COMPRESSION_FASTLZ = 2;

class Couchbase {

    /**
     *
     * @param array $hosts An array of hostnames[:port] where the
     *                     Couchbase cluster is running. The port number is
     *                     optional (and only needed if you're using a non-
     *                     standard port).
     * @param string $user The username used for authentication to
     *                     the cluster
     * @param string $password The password used to authenticate to
     *                       the cluster
     * @param string $bucket The name of the bucket to connect to
     * @param boolean $persistent If a persistent object should be used or not
     */
    function __construct($hosts = array("localhost"), $user = "", $password = "", $bucket = "default", $persistent = false) {

    }

    /**
     * Add a document to the cluster.
     *
     * The add operation adds a document to the cluster only if no document
     * exists in the cluster with the same identifier.
     *
     * @param string $id the identifier to store the document under
     * @param mixed $document the document to store
     * @param integer $expiry the lifetime of the document (0 == infinite)
     * @param integer $persist_to wait until the document is persisted to (at least)
     *                            this many nodes
     * @param integer $replicate_to wait until the document is replicated to (at least)
     *                            this many nodes
     * @return string the cas value of the object if success
     * @throws CouchbaseException if an error occurs
     */
    function add($id, $document, $expiry = 0, $persist_to = 0, $replicate_to = 0) {

    }

    /**
     * Store a document in the cluster.
     *
     * The set operation stores a document in the cluster. It differs from
     * add and replace in that it does not care for the presence of
     * the identifier in the cluster.
     *
     * If the $cas field is specified, set will <b>only</b> succeed if the
     * identifier exists in the cluster with the <b>exact</b> same cas value
     * as the one specified in this request.
     *
     * @param string $id the identifier to store the document under
     * @param mixed $document the document to store
     * @param integer $expiry the lifetime of the document (0 == infinite)
     * @param string $cas a cas identifier to restrict the store operation
     * @param integer $persist_to wait until the document is persisted to (at least)
     *                            this many nodes
     * @param integer $replicate_to wait until the document is replicated to (at least)
     *                            this many nodes
     * @return string the cas value of the object if success
     * @throws CouchbaseException if an error occurs
     */
    function set($key, $document, $expiry = 0, $cas = "", $persist_to = 0, $replicate_to = 0) {

    }

    /**
     * Store multiple documents in the cluster.
     *
     * @param array $documents an array containing "id" =&gt; "document" pairs
     * @param integer $expiry the lifetime of the document (0 == infinite)
     * @param integer $persist_to wait until the document is persisted to (at least)
     *                            this many nodes
     * @param integer $replicate_to wait until the document is replicated to (at least)
     *                            this many nodes
     * @return boolean true if success
     * @throws CouchbaseException if an error occurs
     */
    function setMulti($documents, $expiry = 0, $persist_to = 0, $replicate_to = 0) {

    }

    /**
     * Replace a document in the cluster.
     *
     * The replace operation replaces an existing document in the cluster.
     * It differs from add and set in the way that it requires the precense of
     * the identifier in the cluster.
     *
     * If the $cas field is specified set will <b>only</b> succeed if the
     * identifier exists in the cluster with the <b>exact</b> same cas value
     * as the one specified in this request.
     *
     * @param string $id the identifier to store the document under
     * @param mixed $document the document to store
     * @param integer $expiry the lifetime of the document (0 == infinite)
     * @param string $cas a cas identifier to restrict the replace operation
     * @param integer $persist_to wait until the document is persisted to (at least)
     *                            this many nodes
     * @param integer $replicate_to wait until the document is replicated to (at least)
     *                            this many nodes
     * @return string the cas value of the object if success
     * @throws CouchbaseException if an error occurs
     */
    function replace($id, $document, $expiry = 0, $cas = "", $persist_to = 0, $replicate_to = 0) {

    }

    /**
     * Prepend a document to another document.
     *
     * The prepend operation prepends the attached document to the document
     * already stored in the cluster.
     *
     * If the $cas field is specified prepend will <b>only</b> succeed if the
     * identifier exists in the cluster with the <b>exact</b> same cas value
     * as the one specified in this request.
     *
     * @param string $id identifies the document
     * @param mixed $document the document to prepend
     * @param integer $expiry the lifetime of the document (0 == infinite)
     * @param string $cas a cas identifier to restrict the prepend operation
     * @param integer $persist_to wait until the document is persisted to (at least)
     *                            this many nodes
     * @param integer $replicate_to wait until the document is replicated to (at least)
     *                            this many nodes
     * @return string the cas value of the object if success
     * @throws CouchbaseException if an error occurs
     */
    function prepend($id, $document, $expiry = 0, $cas = "", $persist_to = 0, $replicate_to = 0) {

    }

    /**
     * Append a document to another document.
     *
     * The append operation appends the attached document to the document
     * already stored in the cluster.
     *
     * If the $cas field is specified append will <b>only</b> succeed if the
     * identifier exists in the cluster with the <b>exact</b> same cas value
     * as the one specified in this request.
     *
     * @param string $id identifies the document
     * @param mixed $document the document to append
     * @param integer $expiry the lifetime of the document (0 == infinite)
     * @param string $cas a cas identifier to restrict the append operation
     * @param integer $persist_to wait until the document is persisted to (at least)
     *                            this many nodes
     * @param integer $replicate_to wait until the document is replicated to (at least)
     *                            this many nodes
     * @return string the cas value of the object if success
     * @throws CouchbaseException if an error occurs
     */
    function append($id, $document, $expiry = 0, $cas = "", $persist_to = 0, $replicate_to = 0) {

    }

    /**
     * Please use replace with the $cas field specified.
     */
    function cas($cas, $id, $document, $expiry) {

    }

    /**
     * Retrieve a document from the cluster.
     *
     * @param string $id identifies the object to retrieve
     * @param function $callback a callback function to call for missing
     *                 objects. The function signature looks like:
     *                 <code>bool function($res, $id, &$val)</code>
     *                 if the function returns <code>true</code> the value
     *                 returned through $val is returned. Please note that
     *                 the cas field is not updated in these cases.
     * @param string $cas where to store the cas identifier of the object
     * @return object The document from the cluster
     * @throws CouchbaseException if an error occurs
     */
    function get($id, $callback = NULL, $cas = "") {

    }

    /**
     * Retrieve multiple documents from the cluster.
     *
     * @param array $ids an array containing all of the document identifiers
     * @param array $cas an array to store the cas identifiers of the documents
     * @return array an array containing the documents
     * @throws CouchbaseException if an error occurs
     */
    function getMulti($ids, $cas = array()) {

    }

    /**
     * Retrieve an object from the cache and lock it from modifications.
     *
     * While the object is locked it may only be modified by providing the
     * cas field returned in the cas field. Modifying the object automatically
     * unlocks the object. To manually unlock the object use the unlock()
     * method. All locks is automatically released after a configurable (on the
     * cluster) time interaval.
     *
     * @param string $id identifies the document
     * @param string $cas where to store the cas identifier
     * @param integer $expiry a configuratble lock expiry time (0 == use the
     * value configured on the server).
     * @return object The requested document from the cluster
     * @throws CouchbaseException if an error occurs
     */
    function getAndLock($id, $cas = "", $expiry = 0) {

    }

    /**
     * Retrieve and lock multiple documents from the cache.
     *
     * While the object is locked it may only be modified by providing the
     * cas field returned in the cas field. Modifying the object automatically
     * unlocks the object. To manually unlock the object use the unlock()
     * method. All locks is automatically released after a configurable (on the
     * cluster) time interaval.
     *
     * Bear in mind that locking multiple objects at the same time may not be
     * a good idea and may lead to deadlock ;-)
     *
     * @param array $ids an array containing the identifiers to retrieve
     * @param array $cas where to store the cas identifier
     * @param integer $expiry a configuratble lock expiry time (0 == use the
     * value configured on the server).
     * @return array an array containint the requested documents
     * @throws CouchbaseException if an error occurs
     */
    function getAndLockMulti($ids, $cas = array(), $expiry = 0) {

    }

    /**
     * Retrieve a document from the cluster and update its time to live.
     *
     * @param string $id identifies the document
     * @param integer $expiry the new time to live (0 == infinite)
     * @param string $cas where to store the cas identifier
     * @return object The requested document from the cluster
     * @throws CouchbaseException if an error occurs
     */
    function getAndTouch($id, $expiry = 0, $cas = "") {

    }

    /**
     * Retrieve multiple documents from the cluster and update their time to live.
     *
     * @param array $ids an array containint the document identifiers
     * @param integer $expiry the new time to live (0 == infinite)
     * @param string $cas where to store the cas identifier
     * @return array an array containing the requested documents
     * @throws CouchbaseException if an error occurs
     */
    function getAndTouchMulti($ids, $expiry = 0, $cas = array()) {

    }

    /**
     * Unlock a previously locked document.
     *
     * @param string $id the document to unlock
     * @param string $cas the cas value obtained from getAndLock()
     * @return boolean true upon success
     * @throws CouchbaseException if an error occurs
     */
    function unlock($id, $cas) {

    }

    /**
     * Touch (update time to live) a document in the cluster.
     *
     * @param string $id identifies the document
     * @param integer $expiry the new time to live (0 == infinite)
     * @return boolean true upon success
     * @throws CouchbaseException if an error occurs
     */
    function touch($id, $exptime) {

    }

    /**
     * Touch (update time to live) multiple documents in the cluster.
     *
     * @param array $ids an array containing the document identifiers
     * @param integer $expiry the new time to live (0 == infinite)
     * @return boolean true upon success
     * @throws CouchbaseException if an error occurs
     */
    function touchMulti($ids, $exptime) {

    }

    /**
     * Delete a document from the cluster.
     *
     * @param string $id the document identifier
     * @param string $cas a cas identifier to restrict the store operation
     * @param integer $persist_to wait until the document is persisted to (at least)
     *                            this many nodes
     * @param integer $replicate_to wait until the document is replicated to (at least)
     *                            this many nodes
     * @return string the cas value representing the delete document if success
     * @throws CouchbaseException if an error occurs
     */
    function delete($id, $cas = "", $persist_to = 0, $replicate_to = 0) {

    }

    /**
     * Increment a numeric value in the cluster.
     *
     * If the value isn't created by using increment / decrement, it has to
     * be created as a "textual" string like:
     * <code>$cb-&gt;add("mycounter", "0");</code>. The reason for this is
     * that the operation is performed in the cluster and it has to know
     * the datatype in order to perform the operation.
     *
     * @param string $id the document identifier
     * @param integer $delta the amount to increment
     * @param boolean $create should the value be created if it doesn't exist
     * @param integer $expire the time to live for the document (0 == infinite)
     * @param integer $initial the initial value for the counter if it doesn't exist
     * @return integer the new value upon success
     * @throws CouchbaseException if an error occurs
     */
    function increment($id, $delta = 1, $create = false, $expire = 0, $initial = 0) {

    }

    /**
     * Decrement a numeric value in the cluster.
     *
     * If the value isn't created by using increment / decrement, it has to
     * be created as a "textual" string like:
     * <code>$cb-&gt;add("mycounter", "0");</code>. The reason for this is
     * that the operation is performed in the cluster and it has to know
     * the datatype in order to perform the operation.
     *
     * @param string $id the document identifier
     * @param integer $delta the amount to decrement
     * @param boolean $create should the value be created if it doesn't exist
     * @param integer $expire the time to live for the document (0 == infinite)
     * @param integer $initial the initial value for the counter if it doesn't exist
     * @return integer the new value upon success
     * @throws CouchbaseException if an error occurs
     */
    function decrement($id, $delta = 1, $create = false, $expire = 0, $initial = 0) {

    }

    /**
     * Delete all documents in the bucket.
     *
     * Please note that flush is disabled from the server by default, so it
     * must explicitly be enabled on the bucket. Flush also require the object
     * to be used to contain all the credentials (username, password and
     * bucket name).
     *
     * @return boolean true upon success
     * @throws CouchbaseException if an error occurs
     */
    function flush() {

    }

    /**
     * Issue a get request, but do not wait for responses.
     *
     * @param array $ids the document identifiers to retrieve
     * @param boolean $with_cas if the cas identifier should be retrieved
     * @param function $callback function to call per retrieved document
     * @param integer $expiry lock expiry time
     * @param boolean $lock if the documents should be locked or not
     * @return boolean true upon success, false otherwise
     * @throws CouchbaseException if an error occurs
     */
    function getDelayed($ids, $with_cas = false, $callback = null, $expiry = 0, $lock = false) {

    }

    /**
     * Fetch the one of the received documents requested from getDelayed.
     *
     * @return array an array representing the next document retrieved,
     *               or NULL if there are no more documents.
     * @throws CouchbaseException if an error occurs
     */
    function fetch() {

    }

    /**
     * Fetch the one of the received documents requested from getDelayed.
     *
     * @return array an array representing the documents retrieved,
     *               or NULL if there are no more documents.
     * @throws CouchbaseException if an error occurs
     */
    function fetchAll() {

    }

    /**
     * Execute a view request.
     *
     * The following options are recognized.
     * <table border="1">
     * <tr><th>Name</th><th>Datatype</th></tr>
     * <tr><td>descending</td><td>boolean</td></tr>
     * <tr><td>endkey</td><td>JSON value</td></tr>
     * <tr><td>endkey_docid</td><td>string</td></tr>
     * <tr><td>full_set</td><td>boolean</td></tr>
     * <tr><td>group</td><td>boolean</td></tr>
     * <tr><td>group_level</td><td>numeric</td></tr>
     * <tr><td>inclusive_end</td><td>boolean</td></tr>
     * <tr><td>key</td><td>JSON</td></tr>
     * <tr><td>keys</td><td>JSON array</td></tr>
     * <tr><td>on_error</td><td><code>continue</code> or <code>stop</code></td></tr>
     * <tr><td>reduce</td><td>boolean</td></tr>
     * <tr><td>stale</td><td>boolean</td></tr>
     * <tr><td>skip</td><td>numeric</td></tr>
     * <tr><td>limit</td><td>numeric</td></tr>
     * <tr><td>startkey</td><td>JSON</td></tr>
     * <tr><td>startkey_docid</td><td>string</td></tr>
     * <tr><td>debug</td><td>boolean</td></tr>
     * </table>
     *
     * @todo update the table above with a description.
     * @param string $document The design document containing the view to call
     * @param string $view The view to execute
     * @param array $options extra options to add to the view request (see above)
     * @return array an array with the result of the view request upon success,
     *               or an array containing an error message
     * @throws CouchbaseException if an error occurs
     */
    function view($document, $view = "", $options = array()) {

    }

    /**
     * Generate a view request.
     *
     * @param string $document The design document containing the view to call
     * @param string $view The view to execute
     * @param array $options extra options to add to the view request (see view()
     *                       for more information)
     * @return The generated view request
     * @throws CouchbaseException if an error occurs
     */
    function viewGenQuery($document, $view = "", $options = array()) {

    }

    /**
     * Retrieve statistics information from the cluster.
     *
     * @return array an array containing all "key" =&gt; "value" pairs upon success
     * @throws CouchbaseException if an error occurs
     */
    function getStats() {

    }

    /**
     * Get the last result code from the extension internals.
     *
     * @return integer An error code representing the last error code as
     *         seen by libcouchbase
     */
    function getResultCode() {

    }

    /**
     * Get a textual representation of the last result from the extension
     * internals.
     *
     * @return string An textual string describing last error code as
     *         seen by libcouchbase
     */
    function getResultMessage() {

    }

    /**
     * Update one of the tunables.
     *
     * The following tunables exist:
     * <table border="1">
     * <tr><th>Name</th><th>Description</th></tr>
     * <tr><td>COUCHBASE_OPT_SERIALIZER</td><td>Specifies the serializer to
     * use to store objects in the cluster. The following values may be used:
     * <code>COUCHBASE_SERIALIZER_PHP</code>, <code>COUCHBASE_SERIALIZER_JSON</code>,
     * <code>COUCHBASE_SERIALIZER_JSON_ARRAY</code></td></tr>
     * <tr><td>COUCHBASE_OPT_COMPRESSION</td><td>Specifies the compression to
     * use for big objects. It may be set to the following values:
     * <code>COUCHBASE_COMPRESSION_NONE</code>, <code>COUCHBASE_COMPRESSION_FASTLZ</code>,
     * <code>COUCHBASE_COMPRESSION_ZLIB</code></td></tr>
     * <tr><td>COUCHBASE_OPT_PREFIX_KEY</td><td>A text string used as a prefix
     * for all keys (may be used to create your own namespace).</td></tr>
     * <tr><td>COUCHBASE_OPT_IGNOREFLAGS</td><td>This options is used to disable
     * the deserialisation of the data received from the cluster. It is mainly
     * used by the developers of the extension to be able to test variable
     * parts of the API and should not be used by end users (it may be removed
     * without notice if we find a better way to do this).</td></tr>
     * </table>
     *
     * @param integer $option the option to set
     * @param value $value the new value for the option
     * @throws CouchbaseException if an error occurs (e.g illegal option / value)
     */
    function setOption($option, $value) {

    }

    /**
     * Retrieve the current value of a tunable.
     *
     * @param integer $option the option to retrieve the value for
     * @return value The current value for a tunable. See setOption() for a
     *               description of the legal options to retrieve.
     * @throws CouchbaseException if an error occurs (e.g illegal option)
     */
    function getOption($option) {

    }

    /**
     * Get the version numbers of the memcached servers in the cluster.
     *
     * This method does probably not do what you think it would do. It
     * exists for compatibility with the "memcached" extension. It does
     * <b>not</b> return the Couchbase version used on each node in the
     * cluster, but rather an internal version number from the memcached
     * nodes in the cluster.
     *
     * The easiest way to get the Couchbase version nodes would be
     * something among the lines of:
     *
     * <pre>
     * $cb = new CouchbaseClusterManager("localhost", "Administrator", "secret");
     * $info = json_decode($cb->getInfo());
     * foreach ($info->{"nodes"} as $node) {
     * &nbsp;&nbsp;&nbsp;print $node->{"hostname"} . " is running " . $node->{"version"} . "\n";
     * }
     * </pre>
     * @return array an array containing the memcached version on each node
     * @throws CouchbaseException if an error occurs
     */
    function getVersion() {

    }

    /**
     * Retrieve the version number of the client.
     *
     * @return string client library version number
     */
    function getClientVersion() {

    }

    /**
     * Get the number of replicas configured for the bucket.
     *
     * Note that even if the bucket is configured to use this number of
     * replicas doesn't necessarily mean that this number of replicas exist.
     * It is possible to set the number of replicas higher than the number
     * of nodes.
     *
     * @return integer The number of replicas for the bucket
     * @throws CouchbaseException if an error occurs
     */
    function getNumReplicas() {

    }

    /**
     * Get the name of the servers in the cluster.
     *
     * @return array an array containing all of the servers in the cluster
     * @throws CouchbaseException if an error occurs
     */
    function getServers() {

    }

    /**
     * Get information about a key in the cluster.
     *
     * @param string $id The identifier to get information about
     * @param string $cas The cas for the document to get information about
     * @param array $details an array to store the details about the key
     * @todo update the documentation about the name and meaning of the details
     * @return true on success, false otherwise
     * @throws CouchbaseException if an error occurs
     */
    function observe($id, $cas, $details = array()) {

    }

    /**
     * Get information about multiple keys in the cluster.
     *
     * @param array $ids an array containing the identifiers to look up
     * @param array $detail an array to store the details about the documents
     * @return array with the keys with true on success, false otherwise
     * @throws CouchbaseException if an error occurs
     */
    function observeMulti($ids, $detail = array()) {

    }

    /**
     * Wait for a document to reach a certain state.
     *
     * <table border="1">
     * <tr><th>Name</th><th>Description</th></tr>
     * <tr><td>persist_to</td><td>The number of nodes the document should be
     * persisted to</td></tr>
     * <tr><td>replicate_to</td><td>The number of nodes the document should be
     * replicated to</td></tr>
     * <tr><td>timeout</td><td>The max time to wait for durability</td></tr>
     * <tr><td>interval</td><td>The interval between checking the state of
     * the document</td></tr>
     *
     * </table>
     *
     * @param string $id the identifier for the document to wait for
     * @param string $cas the cas identifier for the document to wait for
     * @param array $details an array containing the details. see above
     * @return true on success, false otherwise
     * @throws CouchbaseException if an error occurs
     */
    function keyDurability($id, $cas, $details = array()) {

    }

    /**
     * Wait for multiple documents to reach a certain state.
     *
     * @param array $ids an array containing "identifier" =&gt; "cas" pairs
     * @param array $detail is an array containing the options to wait for.
     *                      See keyDurability() for a description.
     * @return array with the keys with true on success, false otherwise
     * @throws CouchbaseException if an error occurs
     */
    function keyDurabilityMulti($ids, $detail = array()) {

    }

    /**
     * Retrieve the current operation timeout.
     *
     * @return integer The currently used timeout specified in usec
     */
    function getTimeout() {

    }

    /**
     * Specify an operation timeout.
     *
     * The operation timeout is the time it takes from the command is sent
     * to the cluster and the result should be returned back.
     *
     * @param integer $timeout the new operation timeout specified in usec
     */
    function setTimeout($timeout) {

    }

    /**
     * Get a design document from the cluster.
     *
     * @param string $name The design document to retrieve
     * @return string the content of the document if success
     * @throws CouchbaseException if an error occurs
     */
    function getDesignDoc($name) {

    }

    /**
     * Store a design document in the cluster.
     *
     * Please note that this method will overwrite any existing design document
     * with the same name.
     *
     * @param string $name the name of the design document to store
     * @param string $document the new document to create
     * @return true on success
     * @throws CouchbaseException if an error occurs
     */
    function setDesignDoc($name, $document) {

    }

    /**
     * Delete the named design document from the cluster.
     *
     * @param string $name the name of the design document to delete
     * @return true on success
     * @throws CouchbaseException if an error occurs
     */
    function deleteDesignDoc($name) {

    }

}

class CouchbaseClusterManager {

    /**
     * Create a new instance of the CouchbaseClusterManager.
     *
     * @param array $hosts This is an array of hostnames[:port] where the
     *                     Couchbase cluster is running. The port number is
     *                     optional (and only needed if you're using a non-
     *                     standard port).
     * @param string $user This is the username used for authentications towards
     *                     the cluster
     * @param string $password This is the password used to authenticate towards
     *                       the cluster
     */
    function __construct($hosts, $user, $password) {

    }

    /**
     * Get information about the cluster.
     *
     * @return string a JSON encoded string containing information of the
     *                cluster.
     */
    function getInfo() {

    }

    /**
     * Get information about one (or more) buckets.
     *
     * @param string $name if specified this is the name of the bucket to get
     *                     information about
     * @return string A JSON encoded string containing all information about
     *                the requested bucket(s).
     */
    function getBucketInfo($name = "") {

    }

    /**
     * Create a new bucket in the cluster with a given set of attributes.
     *
     * The bucket may be created with the following attributes:
     * <table border="1">
     * <tr><th>Property</th><th>Description</th></tr>
     * <tr><td>type</td><td>The type of bucket to create. This may be
     *     <code>memcached</code> or <code>couchbase</code></td></tr>
     * <tr><td>auth</td><td>The type of authentication to use to access the
     *     bucket. This may be <code>sasl</code> or <code>none</code>. If
     *  <code>none</code> is used you <b>must</b> specicy a <code>port</code>
     * attribute. for <code>sasl</code> you <b>may</b> specify a
     * <code>password</code> attribute</td></tr>
     * <tr><td>enable flush</td><td>If <code>flush()</code> should be allowed
     *     on the bucket</td></tr>
     * <tr><td>parallel compaction</td><td>If compaction of the database files
     * should be performed in parallel or not (only
     * applicable for <code>couchbase</code> buckets)</td></tr>
     * <tr><td>port</td><td>If the <code>auth</code> attribute is set to
     * <code>none</code> this attribute specifies the port number where
     * clients may access the bucket.</td></tr>
     * <tr><td>quota</td><td>This specifies the amount of memory in MB the bucket
     * should consume on <b>each</b> node in the cluster</td></tr>
     * <tr><td>index replicas</td><td>If replicas should be indexed or not (only
     * applicable for <code>couchbase</code> buckets)</td></tr>
     * <tr><td>replicas</td><td>The number of replicas to create per document.
     * The current version of Couchbase supports the following values: 0, 1, 2 and 3 (only
     * applicable for <code>couchbase</code> buckets)</td></tr>
     * <tr><td>password</td><td>This is the password used to access the bucket if
     * the <code>auth</code> attribute is set to <code>sasl</code></td></tr>
     * </table>
     *
     * @param string $name the name of the bucket to create
     * @param array $attributes a hashtable specifying the attributes for the
     *                          bucket to create.
     */

    function createBucket($name, $attributes) {

    }

    /**
     * Modify the attributes for a given bucket.
     *
     * Please note that you have to specify <b>all</b> attributes for the
     * bucket, so if you want to change a single attribute you should get
     * the current attributes, change the one you want and store the updated
     * attribute set.
     *
     * For a description of the different attribytes, see createBucket()
     *
     * @param string $name the name of the bucket to modify
     * @param array $attributes a hashtable specifying the new attributes for
     *                          the bucket
     */

    function modifyBucket($name, $attributes) {

    }

    /**
     * Delete the named bucket.
     *
     * @param string $name the bucket to delete
     */
    function deleteBucket($name) {

    }

}

////////////////////////////////////////////////////////
//                                                    //
//       The following exception classes exists       //
//                                                    //
////////////////////////////////////////////////////////

class CouchbaseException extends Exception {

}

class CouchbaseIllegalKeyException extends CouchbaseException {

}

class CouchbaseNoSuchKeyException extends CouchbaseException {

}

class CouchbaseAuthenticationException extends CouchbaseException {

}

class CouchbaseLibcouchbaseException extends CouchbaseException {

}

class CouchbaseServerException extends CouchbaseException {

}

class CouchbaseKeyMutatedException extends CouchbaseException {

}

class CouchbaseTimeoutException extends CouchbaseException {

}

class CouchbaseNotEnoughNodesException extends CouchbaseException {

}

class CouchbaseIllegalOptionException extends CouchbaseException {

}

class CouchbaseIllegalValueException extends CouchbaseException {

}


