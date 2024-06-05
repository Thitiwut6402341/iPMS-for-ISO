<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Libraries\JWT\JWTUtils;
use Illuminate\Validation\Rule;
use App\Http\Libraries\Bcrypt;
use Hamcrest\Description;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Illuminate\Support\Facades\Mail;
use App\Mail\Validation;

class SoftwareController extends Controller
{
    private $mongo;
    private $db;
    private $jwtUtils;
    private $bcrypt;

    public function __construct()
    {
        $this->bcrypt = new Bcrypt(10);
        $this->jwtUtils = new JWTUtils();

        $this->mongo = new \MongoDB\Client("mongodb://iiot-center2:%24nc.ii0t%402o2E@10.0.0.3:27017/?authSource=admin");
        $this->db = $this->mongo->selectDatabase("iPMS_ISO_DEV");
    }
    private function MongoDBObjectId($id)
    {
        try {
            return new ObjectId($id);
        } catch (\Exception $e) {
            return null;
        }
    }
    private function MongoDBUTCDatetime(int $time)
    {
        try {
            return new UTCDateTime($time);
        } catch (\Exception $e) {
            return null;
        }
    }

    //! [POST] /software/create
    public function create(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) {
                return response()->json([
                    "status" => "error",
                    "message" => "Unauthorized",
                    "data" => [],
                ], 401);
            }

            $decoded = $jwt->decoded;

            $rules = [
                'project_id' => 'required|string|min:1|max:255',
                'software'   => 'required|string|min:1|max:255',
                'version'    => 'required|string|min:1|max:255',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validator->errors()
                        ]
                    ]
                ], 400);
            }

            $filter = ["_id" => $this->MongoDBObjectId($request->project_id)];
            $options = ["projection" => ["_id" => 0, "project_id" => ['$toString' => '$_id'], "project_name" => 1]];
            $projectDocument = $this->db->selectCollection("Projects")->findOne($filter, $options);

            if (is_null($projectDocument)) {
                return response()->json([
                    "status" => "error",
                    "message" => "Project ID not found",
                    "data" => []
                ], 400);
            }

            $projectID = $projectDocument->project_id;

            $timestamp = $this->MongoDBUTCDateTime(time() * 1000);

            $results = $this->db->selectCollection("Software")->insertOne([
                "project_id" => $this->MongoDBObjectId($projectID),
                "project_name" => $projectDocument->project_name,
                "software" => $request->software,
                "version" => $request->version,
                "creator_id" => $this->MongoDBObjectId($decoded->creater_by),
                "created_at" => $timestamp,
                "updated_at" => $timestamp,
            ]);

            return response()->json([
                "status" => "success",
                "message" => "Insert Software successfully !!",
                "data" => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //! [POST]/software/update
    public function update(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) {
                return response()->json([
                    "status" => "error",
                    "message" => "Unauthorized",
                    "data" => [],
                ], 401);
            }

            $decoded = $jwt->decoded;

            $rules = [
                'software_id' => 'required|string|min:1|max:255',
                'software'    => 'required|string|min:1|max:255',
                'version'     => 'required|string|min:1|max:255',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validator->errors()
                        ]
                    ]
                ], 400);
            }

            $filter = ["_id" => $this->MongoDBObjectId($request->software_id)];
            $options = ["projection" => ["_id" => 0, "software_id" => ['$toString' => '$_id'], "project_id" => ['$toString' => '$project_id'], "project_name" => 1]];
            $softwareDocument = $this->db->selectCollection("Software")->findOne($filter, $options);

            if (is_null($softwareDocument)) {
                return response()->json([
                    "status" => "error",
                    "message" => "Software ID not found",
                    "data" => []
                ], 400);
            }

            $projectID = $softwareDocument->project_id;

            $timestamp = $this->MongoDBUTCDateTime(time() * 1000);

            $results = $this->db->selectCollection("Software")->updateOne(
                ["_id" => $this->MongoDBObjectId($request->software_id)],
                [
                    '$set' => [
                        "software" => $request->software,
                        "version" => $request->version,
                        "updated_at" => $timestamp,
                    ]
                ]
            );

            return response()->json([
                "status" => "success",
                "message" => "Update Software successfully !!",
                "data" => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }
}
