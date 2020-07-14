<?php

/*
    Defines a namespace for the controller.
*/
namespace App\Http\Controllers\Api;

/*
    Defines the requests used by the controller.
*/
use Illuminate\Http\Request;

/*
    Defined controllers used by the controller
*/
use App\Http\Controllers\Controller;

use App\Models\Database;

use MongoDB;

use MongoDB\BSON\Unserializable;

use App\Http\Classes\UnserialiseDocument;


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
     * @var $unserialised MongoDB\Model\BSONArray
     */
    private $unserialised;

    /**
     * Get one or All databases
     *
     * @param $name
     * @return array
     */
    private function getAllDatabases($name = false)
    {
        // only one DB by nae
        if ($name) {
            //$database = (new MongoDB\Client)->$name;
            $database = $this->client->selectDatabase( $name );

         //   echo '<pre>'; var_dump($database); echo '</pre>';

            $stats = $database->command(array('dbstats' => 1))->toArray()[0];
            $statistics = [];
            foreach ($stats as $key => $value) {
                $statistics[ $key ] = $value;
            }

            //echo '<pre>'; var_dump($name); echo '</pre>'; die;
            //echo '<pre>'; var_dump($database->getName()); echo '</pre>'; die;

            $collections = $this->getCollections($name, true);

      //      echo '<pre>'; var_dump($collections); echo '</pre>'; die;

            $arr = array("db" => $database->__debugInfo(), "stats" => $statistics, "collections" => $collections);

         //   echo '<pre>'; var_dump($arr); echo '</pre>'; die;

        } else {
            $arr   = [];
            $index = 0;
            foreach ($this->client->listDatabases() as $db) {
                $dbn = $db->getName();
                $database = (new MongoDB\Client)->$dbn;
                $stats = $database->command(array('dbstats' => 1))->toArray()[0];
                $statistics = [];
                foreach ($stats as $key => $value) {
                    $statistics[ $key ] = $value;
                }
                //    echo '<pre>'; var_dump($statistics); echo '</pre>';
                //   echo '<pre>'; var_dump($this->$this->bsonUnserialize($stats)); echo '</pre>';
                $collections = $this->getCollections($db->getName());
                $arr[]       = array("id" => $index, "db" => $db->__debugInfo(), "stats" => $statistics, "collections" => $collections);
                $index++;
            }
        }
        // !! one result fits all
        return $arr;
    }

    /**
     * @param   string    $db           string DB Name
     * @param   bool      $getObjects
     * @return  array
     */
    private function getCollections($db, $getObjects = false)
    {
        $arr      = [];
        $index    = 0;
        $database = (new MongoDB\Client)->$db;
        /** @var MongoDB\Model\CollectionInfo $collection */
        foreach ($database->listCollections() as $collection) {
        //    echo '<pre>'; var_dump($collection); echo '</pre>'; die;
            if ($getObjects) {
                $arr[] = array("id" => $index, "collection" => $collection->__debugInfo(), "objects" => $this->getObjects($db, $collection->getName()));
            } else {
                $arr[] = array("id" => $index, "collection" => $collection->__debugInfo());
            }
            $index++;
        }
        return $arr;
    }

    /**
     * @param   string  $db
     * @param   string  $collection
     * @return  array
     */
    private function getObjects($db, $collection)
    {
        $arr     = [];
        $cursor  = (new MongoDB\Client)->$db->selectCollection($collection);
        $objects = $cursor->find();
        $arr['objects'] = $objects->toArray();
        $arr['count'] = count($arr['objects']);
        return $arr;
    }

    /**
     * @param string $name
     * @param array $result
     * @return array
     */
    private function setDeleteStatus(string $name, array $result)
    {
        if ($result['dropped'] == $name && $result['ok'] == 1) {
            return array($name => 'success');
        }
        return array($name => 'failed');
    }

    /**
     * DbsController constructor.
     */
    public function __construct()
    {
        $this->client = new MongoDB\Client;
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
        return response()->json( array('databases' => $databases));
    }

    /**
     * Display a single database.
     *
     * URL:         /api/v1/databases/{name}
     * Method:      GET
     * Description: Fetches all databases with full stats
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatabase(Request $request, $name)
    {
        // get the databases
        $database = $this->getAllDatabases($name);
        return response()->json( array('database' => $database));
    }

    /**
     * Creating a new MongoDB database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createDatabase(Request $request)
    {
        $db = $request->get('database');
        // create the database
        $database = (new MongoDB\Client)->$db;
        // ToDo: we need to add a default collection to initialise the DB in MongoDB
        $database->createCollection('foo');

        $index = 0;
        // this lets us build the $index value correctly and re-fetch the new DB so that we cab grab its stats
        // ToDo: their might be a better way to to this - something more efficient - haven't found it yet in the docs
        foreach ($this->client->listDatabases() as $mdb) {
            $index++;
            if ($mdb->getName() == $db) {
                $dbn      = $mdb->getName();
                $database = (new MongoDB\Client)->$dbn;
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

        return response()->json( array('database' => $arr ));
    }

    /**
     * Deleting a MongoDB database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteDatabase(Request $request)
    {
        $names  = $request->get('names', false);
        $status = array();
        if ($names && is_array($names)) {
            foreach ($names as $name) {
                if (!empty($name)) {
                    $db = (new MongoDB\Client)->$name;
                    /** @var MongoDB\Model\BSONDocument $result */
                    $result = $db->drop();
                    $status[] = $this->setDeleteStatus( $name, $result->getArrayCopy());
                 //   echo '<pre>'; var_dump($name); echo '</pre>';
                 //   echo '<pre>'; var_dump($result); echo '</pre>';
                 //   die;
                }
            }
        }

        return response()->json( array('status' => $status ));
    }

    /**
     * @inheritDoc
     */
    public function bsonUnserialize(array $data)
    {
        // TODO: Implement bsonUnserialize() method.
        $this->unserialised = $data;
    }
}
