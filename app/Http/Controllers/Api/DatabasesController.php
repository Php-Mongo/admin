<?php
/**
 * PhpMongoAdmin (www.phpmongoadmin.com) by Masterforms Mobile & Web (MFMAW)
 * @version      DatabasesController.php 1001 6/8/20, 8:53 pm  Gilbert Rehling $
 * @package      PhpMongoAdmin\App
 * @subpackage   DatabasesController.php
 * @link         https://github.com/php-mongo/admin PHP MongoDB Admin
 * @copyright    Copyright (c) 2020. Gilbert Rehling of MMFAW. All rights reserved. (www.mfmaw.com)
 * @licence      PhpMongoAdmin is an Open Source Project released under the GNU GPLv3 license model.
 * @author       Gilbert Rehling:  gilbert@phpmongoadmin.com (www.gilbert-rehling.com)
 *  php-mongo-admin - License conditions:
 *  Contributions to our suggestion box are welcome: https://phpmongotools.com/suggestions
 *  This web application is available as Free Software and has no implied warranty or guarantee of usability.
 *  See licence.txt for the complete licensing outline.
 *  See https://www.gnu.org/licenses/license-list.html for information on GNU General Public License v3.0
 *  See COPYRIGHT.php for copyright notices and further details.
 */

/**
 *  Defines a namespace for the controller.
 */
namespace App\Http\Controllers\Api;

/**
 *  Defines the requests used by the controller.
 */
use Illuminate\Http\Request;
use App\Http\Requests\EditDbAuthRequest as DbAuthRequest;

/**
 *  Defined controllers used by the controller
 */
use App\Http\Controllers\Controller;

/**
 *  Internal classes etc etc
 */
use App\Http\Classes\MongoConnection as Mongo;
use App\Helpers\MongoHelper;
use App\Http\Classes\UnserialiseDocument;

/**
 *  Models
 */
use App\Models\Database;

/**
 *  Mongo DB
 */
use MongoDB;
use MongoDB\BSON\Unserializable;

/**
 * Class DatabasesController
 * @package App\Http\Controllers\Api
 */
class DatabasesController extends Controller implements Unserializable
{
    /**
     * @var null|string $slug
     */
    private $slug = null;

    /**
     * @var int
     */
    private $limit = 30;

    /**
     * @var MongoDB\Client
     */
    private $client;

    /**
     * @var Mongo
     */
    private $mongo;

    /**
     * @var array   $excluded  DB's for exclusion when NON admin user
     */
    private $excluded = ['admin','config','local'];

    /**
     * @var MongoDB\Model\BSONArray $unserialised
     */
    private $unserialised;

    /**
     * @var array|string|null $errorMessage
     */
    private $errorMessage;

    /**
     * @return array|string|null
     */
    private function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @param string|null $errorMessage
     */
    private function setErrorMessage(?string $errorMessage): void
    {
        if ($this->errorMessage) {
            if (is_array($this->errorMessage)) {
                $this->errorMessage[] = $errorMessage;
            } else {
                $this->errorMessage = array(0 => $errorMessage);
            }
        } else {
            $this->errorMessage = $errorMessage;
        }
    }

    /**
     * We need a global method to monitor which database can be read for stats etc
     * For now its just fr the demo website
     * ToDo: !! this can be extended and implemented further later on !!
     *
     * @param   string  $dbn
     * @return  bool
     */
    private function handleExclusions( $dbn ) {
        $env = env('APP_ENV');
        if  ('demo' !== $env) {
            // we  are only checking on the demo site
            return true;
        }
        // still here??
        if (!in_array( $dbn, $this->excluded)) {
            return true;
        }
        return false;
    }

    /**
     * Get one or All databases
     *
     * @param   $name
     * @return  array
     */
    private function getAllDatabases($name = false)
    {
        try {
            // only one DB by name
            if ($name) {
                $database   = $this->client->selectDatabase( $name );
                $stats      = $database->command(array('dbstats' => 1))->toArray()[0];
                $statistics = [];
                // break out the stats into an array
                foreach ($stats as $key => $value) {
                    $statistics[ $key ] = $value;
                }
                // the collections object should contain any relative objects
                $collections = $this->getCollections($name, true);
                $arr         = array("db" => $database->__debugInfo(), "stats" => $statistics, "collections" => $collections);

            } else {

                $arr   = [];
                $index = 0;
                foreach ($this->client->listDatabases() as $db) {
                    $dbn        = $db->getName();
                    // Todo: need to verify which method is the best path
                    // 1) $this->mongo->connectClientDb($dbn)  =  (new MongpDB\Client())->database
                    // 2) $this->client->selectDatabase($dbn)  = (new MongpDB\Client())->selectDatabase('database')
                    $database   = $this->mongo->connectClientDb($dbn);
                    $stats      = [];
                    if ($this->handleExclusions( $dbn)) {
                        // only populate if DB allowed
                        $stats = $database->command(array('dbstats' => 1))->toArray()[0];
                    }
                    $statistics = [];
                    // break out the stats into an array
                    foreach ($stats as $key => $value) {
                        $statistics[ $key ] = $value;
                    }
                    // this set of collections wont need the objects
                    $collections = $this->getCollections($db->getName());
                    $arr[]       = array("id" => $index, "db" => $db->__debugInfo(), "stats" => $statistics, "collections" => $collections);
                    $index++;
                }
            }
            // !! one result fits all
            return $arr;
        }
        catch (\Exception $e) {
            $this->setErrorMessage($e->getMessage());
        }
    }

    /**
     * Returns the collections belonging to each given database
     *
     * @param   string    $db           string DB Name
     * @param   bool      $getObjects
     * @return  array
     */
    private function getCollections($db, $getObjects = false)
    {
        try {
            if (!$this->handleExclusions( $db )) {
                // we get errors when users without correct permissions try to read collections on restricted dbs
                return [];
            }
            $arr      = [];
            $index    = 0;
            $database = $this->client->selectDatabase( $db );
            /** @var MongoDB\Model\CollectionInfo $collection */
            foreach ($database->listCollections() as $collection) {
                // we only need to get objects when its database view
                if ($getObjects) {
                    $arr[] = array("id" => $index, "collection" => $collection->__debugInfo(), "objects" => $this->getObjects($db, $collection->getName()));
                } else {
                    $arr[] = array("id" => $index, "collection" => $collection->__debugInfo());
                }
                $index++;
            }
            return $arr;
        }
       catch (\Exception $e) {
           $this->setErrorMessage($e->getMessage());
           return [];
       }
    }

    /**
     * Returns the objects for the given collection
     *
     * @param   string  $db             string DB Name
     * @param   string  $collection     string Collection name
     * @return  array
     */
    private function getObjects(string $db, string $collection)
    {
        try {
            $arr     = [];
            $cursor  = $this->mongo->connectClientDb($db)->selectCollection($collection);
            $objects = $cursor->find();
            $arr['objects'] = $objects->toArray();
            $arr['count']   = count($arr['objects']);
            return $arr;
        }
        catch (\Exception $e) {
            $this->setErrorMessage($e->getMessage());
            return [];
        }
    }

    /**
     * Used to confirm that a database has been dropped
     *
     * @param string $name
     * @param array $result
     * @return array
     */
    private function setDeleteStatus(string $name, array $result)
    {
        if (1 === $result['ok'] && $name === $result['dropped']) {
            return array($name => 'success');
        }
        return array($name => 'failed');
    }

    /**
     * DatabasesController constructor.
     */
    public function __construct()
    {
        /** @var \App\Models\User $user */
        $user = auth()->guard('api')->user();
        $this->mongo = new Mongo($user);
        if ($this->mongo->checkConfig()) {
            $this->mongo->connectClient();
            $this->client = $this->mongo->getClient();
        }
    }

    /**
     * Display a listing of all databases.
     *
     * URL:         /api/v1/databases
     * Method:      GET
     * Description: Fetches all databases with full stats
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatabases()
    {
        // get the databases
        $databases = $this->getAllDatabases();

        if ($error = $this->getErrorMessage()) {
            // this can occur if there is no Server config
            return response()->error('failed', array('error' => $error));

        } else {
            return response()->success('success', array('databases' => $databases));
        }
    }

    /**
     * Display a single database.
     *
     * URL:         /api/v1/databases/{name}
     * Method:      GET
     * Description: Fetches all databases with full stats
     *
     * @param Request $request
     * @param $name
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatabase(Request $request, $name)
    {
        // get the databases
        $database = $this->getAllDatabases($name);

        if ($error = $this->getErrorMessage()) {
            // this can occur if there is no Server config
            return response()->error('failed', array('error' => $error));

        } else {
            return response()->success('success', array('database' => $database));
        }
    }

    /**
     * Creating a new MongoDB database
     *
     * URL:         /api/v1/databases/create
     * Method:      POST
     * Description: Create a new database using the given name
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createDatabase(Request $request)
    {
        $db = $request->get('database');
        try {
            // create the database
            $database = $this->mongo->connectClientDb($db);

            // ToDo: we need to add a default collection to initialise the DB in MongoDB
            $database->createCollection('foo');

            $index = 0;
            // this lets us build the $index value correctly and re-fetch the new DB so that we cab grab its stats
            // ToDo: their might be a better way to to this - something more efficient - haven't found it yet in the docs
            foreach ($this->client->listDatabases() as $mdb) {
                $index++;
                if ($db === $mdb->getName()) {
                    $dbn      = $mdb->getName();
                    $database = $this->mongo->connectClientDb( $dbn );
                }
            }
            // the index  is used as a key in the front-end
            $index++;

            // get the DB stats
            $stats = $database->command(array('dbstats' => 1))->toArray()[0];
            $statistics = [];
            foreach ($stats as $key => $value) {
                $statistics[ $key ] = $value;
            }
            $arr = array("id" => $index, "db" => $database->__debugInfo(), "stats" => $statistics, "collections" => $this->getCollections($db));

            return response()->success('success', array('database' => $arr ));
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => 'unable to create database ' . $db));
        }
    }

    /**
     * Deleting a MongoDB database
     *
     * URL:         /api/v1/databases/delete
     * Method:      POST
     * Description: Delete the database matching the given name
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteDatabase(Request $request)
    {
        $names  = $request->get('names', false);
        try {
            $status = array();
            if ($names && is_array($names)) {
                foreach ($names as $name) {
                    if (!empty($name)) {
                        $db = $this->mongo->connectClientDb( $name );

                        /** @var MongoDB\Model\BSONDocument $result */
                        $result = $db->drop();
                        $status[] = $this->setDeleteStatus( $name, $result->getArrayCopy());
                    }
                }
            }
            return response()->success('success', array('status' => $status ));
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => 'unable to delete database(s) ' . $names));
        }
    }

    /**
     * Run command against a database
     *
     * URL:         /api/databases/{database}/command
     * Method:      POST
     * Description: Return the results of a database command
     * ToDo: setup a method to analyse the result before returning
     *
     * @param  Request $request
     * @param  $database
     * @return mixed
     *
     * @throws MongoDB\Driver\Exception\Exception
     */
    public function databaseCommand(Request $request, $database)
    {
        try {
            // primitive validation
            if ($request->get('database') === $database) {
                // connect the manager
                $this->mongo->connectManager();
                /** @var MongoDB\Driver\Manager $manager */
                $manager = $this->mongo->getManager();
                $params  = $request->get('params');
                $command = json_decode($params['command'], true);

                /** @var MongoDB\Collection $coll */
                $command = new MongoDB\Driver\Command($command);

                // ToDo !! analyse results and act accordingly !!
                $results = $manager->executeCommand($database, $command);

                // be good be good! - Johnny !!
                return response()->success('success', array('results' => $results->toArray()[0] ));

            } else {
                return response()->error('failed', array('error' => 'database names mismatched'));
            }
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));
        }
    }

    /**
     * Run command against a database
     *
     * URL:         /api/databases/{database}/transfer
     * Method:      POST
     * Description: Return the results of a database transfer
     *
     * @param  Request $request
     * @param  $database
     * @return mixed
     */
    public function databaseTransfer(Request $request, $database) {
        try {
            $params  = $request->get('params', false);
            $db      = $params['database'];
            if ($db === $database) {
                /** @var MongoDB\Client $conn */
                $conn = MongoHelper::remoteConnection($params);

                /** @var MongoDB\Database $dbLink */
                $dbLink = $conn->selectDatabase($params['remoteDatabase']);
                $collections = $dbLink->listCollections();

                $database    = $params['database'];
                $collections = $params['collections'];
                $inserted    = 0;
                // get the manager connection
                /** @var MongoDB\Driver\Manager $man */
                $manager     = $dbLink->getManager();
                foreach ($collections as $collection) {
                    $documents =  MongoHelper::getObjects( $this->client, $database, $collection )['objects'];
                    $inserted += count($documents);
                    // ToDo: enforce the use of the provided remoteDatabase & collection name as the NameSpace for BulkWrite
                    MongoHelper::remoteBulkWrite( $manager, $documents, MongoHelper::ns($params['remoteDatabase'], $collection), $inserted);
                }
                return response()->success('success', array('inserted' => $inserted ));
            }
            else {
                return response()->error('failed', array('error' => 'database names mismatched'));
            }
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));
        }
    }

    /**
     * Save a database logging profile
     *
     * URL:         /api/databases/{database}/profile
     * Method:      POST
     * Description: Save and return a database logging profile
     *
     * @param  Request $request
     * @param  string  $database
     * @return mixed
     */
    public function saveProfile(Request $request, $database)
    {
        try {
            $params  = $request->get('params', false);
            $db      = $params['database'];
            // ToDo: !! if the $level in not an integer the update fails !!
            $level   = (int) $params['level'];
            $slowms  = (int) $params['milliseconds'];
            if ($db === $database) {
                // set the profiling level
                $db     = $this->mongo->connectClientDb( $database );
                $result = $db->command(array('profile' => $level, 'slowms' => $slowms ));

                return response()->success('success', array('result' => $result->toArray()));
            }
            else {
                return response()->error('failed', array('error' => 'database names mismatched'));
            }
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));
        }
    }

    /**
     * Fetch a database logging profile
     *
     * URL:         /api/databases/{database}/profile
     * Method:      GET
     * Description: Fetch a database logging profile
     *
     * @param  Request $request
     * @param  string  $database
     * @return mixed
     */
    public function getProfile(Request $request, $database)
    {
        try {
            if ($database) {
                // get the current profiling level
                $db    = $this->mongo->connectClientDb( $database );
                $level = $db->command(array('profile' => -1))->toArray()[0];

                // get profile data
                $db         = $this->client->selectDatabase( $database );
                $collection = $db->selectCollection('system.profile');
                $documents  = $collection->find();

                return response()->success('success', array('profile' => $documents->toArray(), 'level' => $level ));
            }
            else {
                return response()->error('failed', array('error' => 'database name missing'));
            }
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));
        }
    }

    /**
     * Repair a database
     *
     * URL:         /api/databases/{database}/repair
     * Method:      POST
     * Description: Repair a given database and return result
     *
     * @param Request $request
     * @param $database
     * @return mixed
     */
    public function repairDb(Request $request, $database)
    {
        try {
            $db  = $request->get('database', false);
            if ($db === $database) {
                // get the current profiling level
                $db    = $this->mongo->connectClientDb( $database );
                $result = $db->command(array('repairDatabase' => 1))->toArray()[0];

                return response()->success('success', array('result' => $result ));
            }
            else {
                return response()->error('failed', array('error' => 'database name missing'));
            }
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));
        }
    }

    public function getDbAuth(Request $request, $database)
    {
        try {
            if ($database) {
                // get the db and system collection
                $results = ($this->client)->admin->selectCollection('system.users')->find(['db' => $database]);

                return response()->success('success', array('results' => $results->toArray()));
            }
            else {
                return response()->error('failed', array('error' => 'database name missing'));
            }
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));
        }
    }

    public function saveDbAuth(DbAuthRequest $dbAuthRequest, $database)
    {
        try {
            $data = $dbAuthRequest->validated();
            $db   = $data['database'];
            if ($db === $database) {
                $username = $data['params']['username'];
                $password = $data['params']['password'];
                $readonly = $data['params']['readonly'];
                $update   = $data['params']['update'];

                $this->mongo->connectManager();
                /** @var MongoDB\Driver\Manager $manager */
                $manager = $this->mongo->getManager();

                $roles = array(
                    "role" => "readWrite",
                    "db" => $database
                );

                if ($readonly === true) {
                    $roles = array(
                        "role" => "read",
                        "db" => $database
                    );
                }

                if ($update === true) {
                    $command = array(
                        "updateUser" => $username,
                        "roles" => array(
                            $roles
                        )
                    );

                } else {
                    $command = array(
                        "createUser" => $username,
                        "pwd" => $password,
                        "roles" => array(
                            $roles
                        )
                    );
                }

                $result = $manager->executeCommand(
                    $database,
                    new MongoDb\Driver\Command( $command )
                );
                return response()->success('success', array('results' => $result->toArray() ));
            }
            else {
                return response()->error('failed', array('error' => 'database name missing'));
            }
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));

        } catch (MongoDB\Driver\Exception\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));
        }
    }

    public function deleteDbUser(Request $request, $database)
    {
        try {
            $db  = $request->get('database', false);
            if ($db === $database) {
                $user = $request->get('user', false);
                $arr = explode(".", $user);

                // get connection manager
                $this->mongo->connectManager();
                /** @var MongoDB\Driver\Manager $manager */
                $manager = $this->mongo->getManager();

                $command = array(
                    "dropUser" => $arr[1]
                );
                $result = $manager->executeCommand(
                    $arr[0],
                    new MongoDb\Driver\Command( $command )
                );
                return response()->success('success', array('results' => $result->toArray()));
            }
            else {
                return response()->error('failed', array('error' => 'database name missing'));
            }
        }
        catch (\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));

        } catch (MongoDB\Driver\Exception\Exception $e) {
            return response()->error('failed', array('error' => $e->getMessage()));
        }
    }

    /**
     *
     * @inheritDoc
     */
    public function bsonUnserialize(array $data)
    {
        // TODO: Implement bsonUnserialize() method.
        $this->unserialised = $data;
    }
}
