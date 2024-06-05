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

class TaskController extends Controller
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
    private function randomName(int $length = 10)
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_';
        $pass = array();
        $alphaLength = \strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < $length; $i++) {
            $n = \rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return \implode($pass);
    }

    //* [GET] /task/get-project-issue
    public function getProjectIssue(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            ##main issue
            $pipeline1 =  [
                // ['$match' => ['parent_id' => null]],
                // ['$project' => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'project_id' => 1, 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$cond' => ['if' => ['$eq' => [['$type' => '$$resp.comment_at'], 'date']], 'then' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'else' => '$$resp.comment_at']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                // ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'project_desc']],
                // ['$project' => ['_id' => 0, 'project_id' => 1, 'issue_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'assigned' => 1, 'projecct_type_id' => ['$arrayElemAt' => ['$project_desc.project_type_id', 0]]]],
                // ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'projecct_type_id', 'foreignField' => '_id', 'as' => 'project_type_decs']],
                // ['$project' => ['issue_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => ['$toString' => '$projecct_type_id'], 'cost_estimation' => ['$arrayElemAt' => ['$project_type_decs.cost_estimation', 0]], 'project_type' => ['$arrayElemAt' => ['$project_type_decs.project_type', 0]]]],
                // ['$project' => ['issue_id' => 1, 'project_id' => 1, 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => 1, 'project_type' => 1, 'total_estimation' => ['$sum' => [['$arrayElemAt' => ['$cost_estimation.raw_materials', 0]], ['$arrayElemAt' => ['$cost_estimation.direct_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.overhead_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.gross_profit', 0]]]], 'currency' => ['$arrayElemAt' => ['$cost_estimation.currency', 0]], 'weight_score' => ['$switch' => ['branches' => [['case' => ['$eq' => ['$difficulty_level', 'NEW_COMER']], 'then' => 1], ['case' => ['$eq' => ['$difficulty_level', 'BEGINNER']], 'then' => 2.5], ['case' => ['$eq' => ['$difficulty_level', 'INTERMEDIATE']], 'then' => 5], ['case' => ['$eq' => ['$difficulty_level', 'ADVANCED']], 'then' => 7.5], ['case' => ['$eq' => ['$difficulty_level', 'EXPERT']], 'then' => 10]], 'default' => null]]]], ['$group' => ['_id' => '$project_id', 'elems' => ['$push' => '$$ROOT'], 'sum_weight_by_project' => ['$sum' => '$weight_score']]],
                // ['$unwind' => '$elems'],
                // ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$elems', '$$ROOT']]]],
                // ['$project' => ['_id' => 0, 'elems' => 0]],
                // ['$addFields' => ['task_cost' => ['$divide' => [['$multiply' => ['$total_estimation', '$weight_score']], '$sum_weight_by_project']]]],

                ['$match' => ['parent_id' => null]],
                ['$project' => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'project_id' => 1, 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$cond' => ['if' => ['$eq' => [['$type' => '$$resp.comment_at'], 'date']], 'then' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'else' => '$$resp.comment_at']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'project_desc']],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'issue_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'assigned' => 1, 'projecct_type_id' => ['$arrayElemAt' => ['$project_desc.project_type_id', 0]]]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'project_planing', 'pipeline' => [['$project' => ['_id' => 0, 'project_planing_id' => '$_id', 'selling_prices' => '$selling_prices']]]]],
                ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'projecct_type_id', 'foreignField' => '_id', 'as' => 'project_type_decs']],
                ['$project' => ['issue_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => ['$toString' => '$projecct_type_id'], 'project_type' => ['$arrayElemAt' => ['$project_type_decs.project_type', 0]], 'selling_prices' => ['$arrayElemAt' => ['$project_planing.selling_prices', 0]], 'project_planing_id' => ['$arrayElemAt' => ['$project_planing.project_planing_id', 0]]]],
                ['$project' => ['issue_id' => 1, 'project_id' => 1, 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => 1, 'project_type' => 1, 'selling_prices' => 1, 'project_planing_id' => ['$toString' => '$project_planing_id'], 'weight_score' => ['$switch' => ['branches' => [['case' => ['$eq' => ['$difficulty_level', 'NEW_COMER']], 'then' => 2], ['case' => ['$eq' => ['$difficulty_level', 'BEGINNER']], 'then' => 4], ['case' => ['$eq' => ['$difficulty_level', 'INTERMEDIATE']], 'then' => 6], ['case' => ['$eq' => ['$difficulty_level', 'ADVANCED']], 'then' => 8], ['case' => ['$eq' => ['$difficulty_level', 'EXPERT']], 'then' => 10]], 'default' => null]]]],
                ['$group' => ['_id' => '$project_id', 'elems' => ['$push' => '$$ROOT'], 'sum_weight_by_project' => ['$sum' => '$weight_score']]],
                ['$unwind' => '$elems'],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$elems', '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'elems' => 0]],
                ['$addFields' => ['task_cost' => ['$divide' => [['$multiply' => ['$selling_prices', '$weight_score']], '$sum_weight_by_project']]]]
            ];

            $result1 = $this->db->selectCollection('ProjectsIssue')->aggregate($pipeline1);
            ##sub issue
            $pipeline2 =  [
                ['$match' => ['parent_id' => ['$ne' => null]]],
                ['$project' => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'project_id' => 1, 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$cond' => ['if' => ['$eq' => [['$type' => '$$resp.comment_at'], 'date']], 'then' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'else' => '$$resp.comment_at']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'project_desc']],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'issue_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'assigned' => 1, 'projecct_type_id' => ['$arrayElemAt' => ['$project_desc.project_type_id', 0]]]],
                ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'projecct_type_id', 'foreignField' => '_id', 'as' => 'project_type_decs']],
                ['$project' => ['issue_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'assigned' => 1, 'projecct_type_id' => ['$toString' => '$projecct_type_id'], 'project_type' => ['$arrayElemAt' => ['$project_type_decs.project_type', 0]]]]
            ];

            $result2 = $this->db->selectCollection('ProjectsIssue')->aggregate($pipeline2);

            $data1 = array();
            foreach ($result1 as $doc) \array_push($data1, $doc);

            $data2 = array();
            foreach ($result2 as $doc) \array_push($data2, $doc);

            $data = array_merge($data1, $data2); //merge 2 array (main issue and sub issue)

            return response()->json([
                "status" => "success",
                "message" => "Get all porject issue successfully !!",
                "data" => $data
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [GET] /task/get-project-main-issue
    public function getProjectMainIssue(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $pipeline = [
                // ['$match' => ['parent_id' => null]],
                // ['$project' => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'project_id' => 1, 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$cond' => ['if' => ['$eq' => [['$type' => '$$resp.comment_at'], 'date']], 'then' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'else' => '$$resp.comment_at']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                // ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'project_desc']],
                // ['$project' => ['_id' => 0, 'project_id' => 1, 'issue_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => ['$arrayElemAt' => ['$project_desc.project_type_id', 0]]]],
                // ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'projecct_type_id', 'foreignField' => '_id', 'as' => 'project_type_decs']],
                // ['$project' => ['issue_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => ['$toString' => '$projecct_type_id'], 'cost_estimation' => ['$arrayElemAt' => ['$project_type_decs.cost_estimation', 0]], 'project_type' => ['$arrayElemAt' => ['$project_type_decs.project_type', 0]]]],
                // ['$project' => ['issue_id' => 1, 'project_id' => 1, 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => 1, 'project_type' => 1, 'total_estimation' => ['$sum' => [['$arrayElemAt' => ['$cost_estimation.raw_materials', 0]], ['$arrayElemAt' => ['$cost_estimation.direct_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.overhead_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.gross_profit', 0]]]], 'currency' => ['$arrayElemAt' => ['$cost_estimation.currency', 0]], 'weight_score' => ['$switch' => ['branches' => [['case' => ['$eq' => ['$difficulty_level', 'NEW_COMER']], 'then' => 1], ['case' => ['$eq' => ['$difficulty_level', 'BEGINNER']], 'then' => 2.5], ['case' => ['$eq' => ['$difficulty_level', 'INTERMEDIATE']], 'then' => 5], ['case' => ['$eq' => ['$difficulty_level', 'ADVANCED']], 'then' => 7.5], ['case' => ['$eq' => ['$difficulty_level', 'EXPERT']], 'then' => 10]], 'default' => null]]]],
                // ['$group' => ['_id' => '$project_id', 'elems' => ['$push' => '$$ROOT'], 'sum_weight_by_project' => ['$sum' => '$weight_score']]],
                // ['$unwind' => '$elems'],
                // ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$elems', '$$ROOT']]]],
                // ['$project' => ['_id' => 0, 'elems' => 0]],
                // ['$addFields' => ['task_cost' => ['$divide' => [['$multiply' => ['$total_estimation', '$weight_score']], '$sum_weight_by_project']]]]

                ['$match' => ['parent_id' => null]],
                ['$project' => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'project_id' => 1, 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$cond' => ['if' => ['$eq' => [['$type' => '$$resp.comment_at'], 'date']], 'then' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'else' => '$$resp.comment_at']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'project_desc']],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'issue_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'assigned' => 1, 'projecct_type_id' => ['$arrayElemAt' => ['$project_desc.project_type_id', 0]]]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'project_planing', 'pipeline' => [['$project' => ['_id' => 0, 'project_planing_id' => '$_id', 'selling_prices' => '$selling_prices']]]]],
                ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'projecct_type_id', 'foreignField' => '_id', 'as' => 'project_type_decs']],
                ['$project' => ['issue_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => ['$toString' => '$projecct_type_id'], 'project_type' => ['$arrayElemAt' => ['$project_type_decs.project_type', 0]], 'selling_prices' => ['$arrayElemAt' => ['$project_planing.selling_prices', 0]], 'project_planing_id' => ['$arrayElemAt' => ['$project_planing.project_planing_id', 0]]]],
                ['$project' => ['issue_id' => 1, 'project_id' => 1, 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => 1, 'project_type' => 1, 'selling_prices' => 1, 'project_planing_id' => ['$toString' => '$project_planing_id'], 'weight_score' => ['$switch' => ['branches' => [['case' => ['$eq' => ['$difficulty_level', 'NEW_COMER']], 'then' => 2], ['case' => ['$eq' => ['$difficulty_level', 'BEGINNER']], 'then' => 4], ['case' => ['$eq' => ['$difficulty_level', 'INTERMEDIATE']], 'then' => 6], ['case' => ['$eq' => ['$difficulty_level', 'ADVANCED']], 'then' => 8], ['case' => ['$eq' => ['$difficulty_level', 'EXPERT']], 'then' => 10]], 'default' => null]]]],
                ['$group' => ['_id' => '$project_id', 'elems' => ['$push' => '$$ROOT'], 'sum_weight_by_project' => ['$sum' => '$weight_score']]],
                ['$unwind' => '$elems'],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$elems', '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'elems' => 0]],
                ['$addFields' => ['task_cost' => ['$divide' => [['$multiply' => ['$selling_prices', '$weight_score']], '$sum_weight_by_project']]]]
            ];
            $result = $this->db->selectCollection('ProjectsIssue')->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all main porject issue successfully !!",
                "data" => $data
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //Main Task
    //* [POST] /task/add-project-issue
    public function addProjectIssue(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $decoded = $jwt->decoded;
            $userID = $decoded->user_id;

            $rules = [
                'project_id'             => 'required | string | min:1 | max:50',
                'parent_id'              => 'nullable | string | min:1 | max:50',
                'teamspace_id'           => 'required | string | min:1 | max:100',
                "issue_name"             => 'required | string | min:1 | max:255',
                "description"            => 'required | string',
                "iso_process"            => 'required | string | min:1 | max:255',
                "difficulty_level"       => ['required', 'string', Rule::in(["NEW_COMER", "BEGINNER", "INTERMEDIATE", "ADVANCED", "EXPERT"])],
                "status_group"           => ['required', 'string', Rule::in(["backlog", "unstarted", "started", "completed", "canceled"])],
                "status"                 => ['required', 'string', 'min:1', 'max:200'],
                "assigned"               => 'required | array', //! need to recorde in Object ID
                "priority"               => ['required', 'string', Rule::in(["high", "medium", "low", "urgent", "none"])],
                "labels"                 => 'nullable | array ',
                "tags"                   => 'nullable | string | min:1 | max:100',
                "require_check_by"       => 'nullable | string | min:1 | max:100',
                "link"                   => 'nullable | array',
                "comments"               => 'nullable | array',
                "start_date"             => 'nullable | string ',
                "end_date"               => 'nullable | string',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            //! Check data
            $filter = ["_id" => $this->MongoDBObjectId($request->project_id)];
            $options = ["projection" => ["_id" => 0, "project_id" => ['$toString' => '$_id']]];

            $chkProjectID = $this->db->selectCollection("Projects")->find($filter, $options);
            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Project ID not found", "data" => []], 500);

            $projectID  = $request->project_id;


            $filter2 = ["_id" => $this->MongoDBObjectId($request->teamspace_id)];
            $opions2 = ["projection" => ["_id" => 0, "teamspace_id" => ['$toString' => '$_id'], "teamspace_code" => 1]];

            $chkTeamspaceID = $this->db->selectCollection("Teamspaces")->find(["_id" => $this->MongoDBObjectId($request->teamspace_id)], ["projection" => ["_id" => 0, "teamspace_id" => ['$toString' => '$_id'], "teamspace_code" => 1]]);
            $dataChk2 = array();
            foreach ($chkTeamspaceID as $info) \array_push($dataChk2, $info);
            if (\count($dataChk2) == 0)
                return response()->json(["status" => "error", "message" => "Teamspace ID not found", "data" => []], 500);
            //! Check data

            $teamspaceID      = $this->db->selectCollection("Teamspaces")->findOne($filter2, $opions2)->teamspace_id;

            $parentID           = $request->parent_id;
            $issueName          = $request->issue_name;
            $description        = $request->description;
            $isoProcess         = $request->iso_process;
            $difficultyLevel    = $request->difficulty_level;
            $status             = $request->status;
            $statusGroup        = $request->status_group;
            $assigned           = $request->assigned;
            $priority           = $request->priority;
            $labels             = $request->labels;
            $tags               = $request->tags;
            $requireCheck       = $request->require_check_by;
            $startDate          = $request->start_date;
            $endDate            = $request->end_date;
            $link               = $request->link;
            $comments           = $request->comments;

            $dataAssigned = [];
            foreach ($assigned as $doc) \array_push($dataAssigned, $this->MongoDBObjectId($doc));

            $dataListComments = [];
            if (!is_null($comments)) {
                $dataListCommentNew = [];
                foreach ($comments as $info) \array_push($dataListCommentNew, $info);
                for ($i = 0; $i < count($comments); $i++) {
                    $list = [
                        "user_id"      => $this->MongoDBObjectId($userID),
                        "comment"      => $dataListCommentNew[$i]["comment"],
                        "comment_at"   => $timestamp,
                    ];
                    array_push($dataListComments, $list);
                };
            } else {
                $dataListComments = null;
            }

            $dataListLink = [];
            if (!is_null($link)) {
                $dataLink = [];
                foreach ($link as $j) \array_push($dataLink, $j);
                for ($i = 0; $i < count($link); $i++) {
                    $list = [
                        "title"      => $dataLink[$i]["title"],
                        "url"      => $dataLink[$i]["url"],
                    ];
                    array_push($dataListLink, $list);
                };
            } else {
                $dataListLink = null;
            }

            if ($parentID != null) {
                $parentID = $this->MongoDBObjectId($parentID);
            } else {
                $parentID = null;
            }

            if ($requireCheck != null) {
                $requireCheck = $this->MongoDBObjectId($requireCheck);
            } else {
                $requireCheck = null;
            }

            $document = array(
                "project_id"                => $this->MongoDBObjectId($projectID),
                "parent_id"                 => $parentID,
                "teamspace_id"              => $this->MongoDBObjectId($teamspaceID),
                "issue_name"                => $issueName,
                "description"               => $description,
                "iso_process"               => $isoProcess,
                "difficulty_level"          => $difficultyLevel,
                "status_group"              => $statusGroup,
                "status"                    => $status,
                "assigned"                  => $dataAssigned,
                "priority"                  => $priority,
                "labels"                    => $labels,
                "tags"                      => $tags,
                "require_check_by"          => $requireCheck,
                "link"                      => $dataListLink,
                "comments"                  => $dataListComments,
                "create_by"                 => $this->MongoDBObjectId($decoded->creater_by),
                "start_date"                => $startDate,
                "end_date"                  => $endDate,
                "created_at"                => $timestamp,
                "is_approved"                => null,
            );

            $result = $this->db->selectCollection("ProjectsIssue")->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Add project issue successfully !!",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //* [PUT] /task/edit-project-issue
    public function editProjectIssue(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $decoded = $jwt->decoded;
            $userID = $decoded->user_id;

            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);
            $rules = [
                'issue_id'   => 'required | string | min:1 | max:255',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $issueID     = $request->issue_id;

            $filter3 = ["_id" => $this->MongoDBObjectId($issueID)]; //, "TeamCode" => $decoded->TeamCode
            $options3 = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "issue_id"      => ['$toString' => '$_id'],
                    "project_id"    => ['$toString' => '$project_id'],
                ]
            ];

            $chkProjectIssueID = $this->db->selectCollection("ProjectsIssue")->find($filter3, $options3);

            $dataChk = array();
            foreach ($chkProjectIssueID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Issue id not found ", "data" => []], 400);


            $parentID           = $request->parent_id;
            $issueName          = $request->issue_name;
            $description        = $request->description;
            $isoProcess         = $request->iso_process;
            $difficultyLevel    = $request->difficulty_level;
            $status             = $request->status;
            $statusGroup        = $request->status_group;
            $assigned           = $request->assigned;
            $priority           = $request->priority;
            $labels             = $request->labels;
            $tags               = $request->tags;
            $requireCheck       = $request->require_check_by;
            $startDate          = $request->start_date;
            $endDate            = $request->end_date;
            $link               = $request->link;
            $comments           = $request->comments;

            $dataAssigned = [];
            foreach ($assigned as $doc) \array_push($dataAssigned, $this->MongoDBObjectId($doc));

            // $queryOldData = $this->db->selectCollection("ProjectsIssue")->findOne(
            //     ["_id"=>$this->MongoDBObjectId($request->issue_id)],
            //     ["projection" =>["_id" => 0,"comments"=>1,"link"=>1]]);

            // $commentsPrevious =[];
            // if(!is_null($queryOldData->comments)){
            //     $commentsPrevious = $queryOldData->comments;
            // }else{$commentsPrevious = null;}
            // $linkPrevious = [];
            // if(!is_null($queryOldData->link)){
            //     $linkPrevious = $queryOldData->link;
            // }else{$linkPrevious = null;}

            ##Comments new
            $dataListComments = [];
            if (!is_null($comments)) {
                $dataListCommentNew = [];
                foreach ($comments as $info) \array_push($dataListCommentNew, $info);
                for ($i = 0; $i < count($comments); $i++) {
                    $list = [
                        "user_id"      => $this->MongoDBObjectId($userID),
                        "comment"      => $dataListCommentNew[$i]["comment"],
                        "comment_at"   => $timestamp,
                    ];
                    array_push($dataListComments, $list);
                };
            } else {
                $dataListComments = null;
            }

            // $dataListComments = array_merge((array)$dataListComments,(array)$commentsPrevious);

            ##Link
            $dataListLink = [];
            if (!is_null($link)) {
                $dataLink = [];
                foreach ($link as $j) \array_push($dataLink, $j);
                for ($i = 0; $i < count($link); $i++) {
                    $list = [
                        "title"      => $dataLink[$i]["title"],
                        "url"      => $dataLink[$i]["url"],
                    ];
                    array_push($dataListLink, $list);
                };
            } else {
                $dataListLink = null;
            }

            // $dataListLink = array_merge((array)$dataListLink,(array)$linkPrevious);

            if ($requireCheck != null) {
                $requireCheck = $this->MongoDBObjectId($requireCheck);
            } else {
                $requireCheck = null;
            }

            $update = array(
                "parent_id"                 => $parentID,
                "issue_name"                => $issueName,
                "description"               => $description,
                "iso_process"               => $isoProcess,
                "difficulty_level"          => $difficultyLevel,
                "status_group"              => $statusGroup,
                "status"                    => $status,
                "assigned"                  => $dataAssigned,
                "priority"                  => $priority,
                "labels"                    => $labels,
                "tags"                      => $tags,
                "require_check_by"          => $requireCheck,
                "link"                      => $dataListLink,
                "comments"                  => $dataListComments,
                "start_date"                => $startDate,
                "end_date"                  => $endDate,
                "updated_at"                => $timestamp,
            );

            $result = $this->db->selectCollection("ProjectsIssue")->updateOne($filter3, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "ํYou edit project issue successfully !!",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [DELETE] /task/delete-project-issue
    public function deleteProjectIssue(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $decoded = $jwt->decoded;

            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);
            $rules = [
                'issue_id'         => 'required | string |min:1|max:255',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');

            $projectIssueID     = $request->issue_id;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($projectIssueID)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "issue_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "teamspace_id" => ['$toString' => '$teamspace_id'],
                ]
            ];

            $chkProjectIssueID = $this->db->selectCollection("ProjectsIssue")->find($filter, $options);

            $dataChk = array();
            foreach ($chkProjectIssueID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Issue ID not found", "data" => []], 500);
            //! check data

            $result = $this->db->selectCollection("ProjectsIssue")->deleteOne($filter,);

            if ($result->getDeletedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data deletion",
                    "data" => [],
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Delete project issue successfully !!",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], $statusCode);
        }
    }

    // ********************************************* Status Tasks ************************************************************
    //? [PUT] /task/change-status-task
    public function changeStatusTask(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $decoded = $jwt->decoded;
            $userID = $decoded->user_id;

            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);
            $rules = [
                'issue_id'         => 'required | string |min:1|max:255',
                'status_group'       => 'required | string |min:1|max:255',
                'status'         => 'required | string |min:1|max:255',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $issueID     = $request->issue_id;
            $status_group   = $request->status_group;
            $status         = $request->status;
            //new ============================================================================================================
            # get assigned from database
            $filter = ["_id" => $this->MongoDBObjectId($issueID)];
            $piplelineAssignee = [
                ['$match' => ['_id' => $this->MongoDBObjectId($issueID)]],
                ['$project' => ['_id' => 0, 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'item', 'in' => ['$toString' => '$$item']]], 'require_check_by' => ['$toString' => '$require_check_by']]]
            ];
            $resultAssignee = $this->db->selectCollection('ProjectsIssue')->aggregate($piplelineAssignee);

            $dataAssignee = array();
            foreach ($resultAssignee as $doc) \array_push($dataAssignee, $doc);

            // return response()->json($dataAssignee[0]->assigned);
            // return response()->json($userID);

            #check user id in dataAssignee
            if (in_array($userID, (array)$dataAssignee[0]->assigned)) {
                if ($status == 'Done') {
                    if ($dataAssignee[0]->require_check_by != null) {
                        $update = [
                            "status_group"  => "started",
                            "status"        => "In Review",
                            "updated_at"    => $timestamp,
                        ];
                        $result = $this->db->selectCollection('ProjectsIssue')->updateOne($filter, ['$set' => $update]);
                        return response()->json([
                            "status" => "success",
                            "message" => "Please waiting for inspector approval",
                            "data" => [$result]
                        ], 200);
                    } else {
                        $update = [
                            "status_group"  => $status_group,
                            "status"        => $status,
                            "is_approved"   => true,
                            "updated_at"    => $timestamp,
                        ];
                        $result = $this->db->selectCollection('ProjectsIssue')->updateOne($filter, ['$set' => $update]);
                        return response()->json([
                            "status" => "success",
                            "message" => "Done",
                            "data" => [$result]
                        ], 200);
                    }
                } else if ($status_group == "canceled") {
                    $update = [
                        "status_group"  => $status_group,
                        "status"        => $status,
                        "is_approved"    => false,
                        "updated_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection('ProjectsIssue')->updateOne($filter, ['$set' => $update]);
                    return response()->json([
                        "status" => "success",
                        "message" => "canceled",
                        "data" => [$result]
                    ], 200);
                } else {
                    $update = [
                        "status_group"  => $status_group,
                        "status"        => $status,
                        "updated_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection('ProjectsIssue')->updateOne($filter, ['$set' => $update]);
                    return response()->json([
                        "status" => "success",
                        "message" => "Edit status successfully !!",
                        "data" => [$result]
                    ], 200);
                }
            } else {
                return response()->json([
                    "status" => "error",
                    "message" => "You are not assigned with this task",
                    "data" => []
                ], 401);
            }
            //============================================================================================================

        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //? [GET] /task/get-waiting-review
    public function getWaitingReview(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $decoded = $jwt->decoded;
            $userID = $decoded->user_id;
            // return $userID;

            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

            // ใช้แทน decode token ไปก่อน
            // $rules = [
            //     'require_check_by'         => 'required | string |min:1|max:255',
            // ];

            // $validators = Validator::make($request->all(), $rules);

            // if ($validators -> fails()) {
            //     return response()->json([
            //         "status" => "error",
            //         "message" => "Bad request",
            //         "data" => [
            //             [
            //                 "validator" => $validators -> errors()
            //             ]
            //         ]
            //     ], 400);
            // }

            // $requireCheckBy = $request -> require_check_by;

            $pipeline = [
                ['$match' => ["require_check_by" => $this->MongoDBObjectId($userID)]],
                ['$match' => ["status" => "In Review"]],
                ['$project' => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'sub_issue' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
            ];

            $result = $this->db->selectCollection('ProjectsIssue')->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all sub issue successfully !!",
                "data" => $data
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], $statusCode);
        }
    }

    //? [UPDATE] /task/interviewer-approval
    public function interviewerApproval(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $decoded = $jwt->decoded;
            $userID = $decoded->user_id;

            $rules = [
                'issue_id'      => 'required | string |min:1|max:255',
                "status_group"  => 'required | string |min:1|max:255',
                'status'        => 'required | string |min:1|max:255',
                'comments'       => 'nullable | array',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $issueID     = $request->issue_id;
            $status_group   = $request->status_group;
            $status         = $request->status;
            $comments       = $request->comments;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($issueID)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "issue_id" => ['$toString' => '$_id'], "issue_name" => 1, "status_group" => 1, "status" => 1, "is_approved" => 1, "require_check_by" => ['$toString' => '$require_check_by'], "comments" => 1]];

            $chkProjectIssueID = $this->db->selectCollection("ProjectsIssue")->find($filter, $options);

            $dataChk = array();
            foreach ($chkProjectIssueID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "Issue id not found",
                    "data" => []
                ], 500);
            }
            //! check data

            $queryOldData = $this->db->selectCollection("ProjectsIssue")->findOne(
                ["_id" => $this->MongoDBObjectId($request->issue_id)],
                ["projection" => ["_id" => 0, "comments" => 1, "link" => 1]]
            );

            $commentsPrevious = [];
            if (!is_null($queryOldData->comments)) {
                $commentsPrevious = $queryOldData->comments;
            } else {
                $commentsPrevious = null;
            }
            $dataListComments = [];
            if ($comments != null) {
                $dataListCommentNew = [];
                foreach ($comments as $info) \array_push($dataListCommentNew, $info);
                for ($i = 0; $i < count($comments); $i++) {
                    $list = [
                        "user_id"      => $this->MongoDBObjectId($userID),
                        "comment"      => $dataListCommentNew[$i]["comment"],
                        "comment_at"   => $timestamp,
                    ];
                    array_push($dataListComments, $list);
                };
            } else {
                $dataListComments = null;
            }
            $dataListComments = array_merge((array)$dataListComments, (array)$commentsPrevious);

            if ($status_group == 'completed') {
                $update = [
                    "status_group"  => $status_group,
                    "status"        => $status,
                    // "comments"      => $dataListComments,
                    "is_approved"    => true,
                    "updated_at"    => $timestamp,
                ];

                $result = $this->db->selectCollection('ProjectsIssue')->updateOne($filter, ['$set' => $update]);

                return response()->json([
                    "status" => "success",
                    "message" => "Approved",
                    "data" => [$result]
                ], 200);
            } else if ($status_group == 'canceled') {
                $update = [
                    "status_group"  => $status_group,
                    "status"        => $status,
                    "comments"      => $dataListComments,
                    "is_approved"    => false,
                    "updated_at"    => $timestamp,
                ];

                $result = $this->db->selectCollection('ProjectsIssue')->updateOne($filter, ['$set' => $update]);

                return response()->json([
                    "status" => "success",
                    "message" => "Rejected",
                    "data" => [$result]
                ], 200);
            } else {
                $update = [
                    "status_group"  => $status_group,
                    "status"        => $status,
                    "comments"      => $dataListComments,
                    "updated_at"    => $timestamp,
                ];

                $result = $this->db->selectCollection('ProjectsIssue')->updateOne($filter, ['$set' => $update]);

                return response()->json([
                    "status" => "success",
                    "message" => "Edit sub issue successfully !!",
                    "data" => [$result]
                ], 200);
            }
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //? [UPDATE] /task/comment-task
    public function commentTask(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $decoded = $jwt->decoded;
            $userID = $decoded->user_id;

            $rules = [
                'issue_id'      => 'required | string |min:1|max:255',
                'comments'       => 'nullable | array ',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $issueID     = $request->issue_id;
            $comments     = $request->comments;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($issueID)];
            $options = ["limit" => 1, "projection" => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'comments' => 1]];
            $chkProjectIssueID = $this->db->selectCollection("ProjectsIssue")->find($filter, $options);

            $dataChk = array();
            foreach ($chkProjectIssueID as $doc) \array_push($dataChk, $doc);
            // $commentsPrevious = $dataChk[0]->comments;

            if (\count($dataChk) == 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "Issue id not found",
                    "data" => []
                ], 500);
            }
            //! check data

            ##Comments
            $dataListComments = [];
            if (!is_null($comments)) {
                $dataListCommentNew = [];
                foreach ($comments as $info) \array_push($dataListCommentNew, $info);
                for ($i = 0; $i < count($comments); $i++) {
                    $list = [
                        "user_id"      => $this->MongoDBObjectId($userID),
                        "comment"      => $dataListCommentNew[$i]["comment"],
                        "comment_at"   => $timestamp,
                    ];
                    array_push($dataListComments, $list);
                };
            } else {
                $dataListComments = null;
            }

            // $dataListComments = array_merge((array)$dataListComments,(array)$commentsPrevious);
            $update = array(
                "comments"      => $dataListComments,
                "updated_at"    => $timestamp,
            );

            $result = $this->db->selectCollection('ProjectsIssue')->updateOne($filter, ['$set' => $update]);
            return response()->json([
                "status" => "success",
                "message" => "Comment issue successfully !!",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //? [POST] //task/add-template
    public function addTemplate(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $decoded = $jwt->decoded;

            $rules = [
                'project_id'      => 'required | string |min:1|max:255',
                'teamspace_id'    => 'required | string | min:1 | max:100',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            $projectID  = $request->project_id;
            $teamspaceID = $request->teamspace_id;

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            //! Check data
            $filter = ["_id" => $this->MongoDBObjectId($projectID)];
            $options = ["projection" => ["_id" => 0, "project_id" => ['$toString' => '$_id']]];

            $chkProjectID = $this->db->selectCollection("Projects")->find($filter, $options);
            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Project ID not found", "data" => []], 400);

            $filter2 = ["_id" => $this->MongoDBObjectId($teamspaceID)];
            $opions2 = ["projection" => ["_id" => 0, "teamspace_id" => ['$toString' => '$_id'], "teamspace_code" => 1]];

            $chkTeamspaceID = $this->db->selectCollection("Teamspaces")->find(["_id" => $this->MongoDBObjectId($request->teamspace_id)], ["projection" => ["_id" => 0, "teamspace_id" => ['$toString' => '$_id'], "teamspace_code" => 1]]);
            $dataChk2 = array();
            foreach ($chkTeamspaceID as $info) \array_push($dataChk2, $info);
            if (\count($dataChk2) == 0)
                return response()->json(["status" => "error", "message" => "Teamspace ID not found", "data" => []], 400);
            $teamspaceID      = $this->db->selectCollection("Teamspaces")->findOne($filter2, $opions2)->teamspace_id;
            //! Check data

            ## get start date and end date from StatementOfWork
            $pipeline_SOW = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'start_date' => 1]],
                ['$sort'    => ['_id' => -1]],
                ['$limit' => 1]
            ];

            $result_SOW = $this->db->selectCollection('StatementOfWork')->aggregate($pipeline_SOW);
            $data_SOW = array();
            foreach ($result_SOW as $doc) \array_push($data_SOW, $doc);

            $startDate = $data_SOW[0]->start_date;

            ## Template
            $projectPlaning = ["Internal Kick-off", "Generate Statement of Work", "Generate Project Plan", "Verify Project Plan", "Kick-off Material", "Open job order in SAP"];
            $softwareAnalysis = ["Analyze user requirements", "Generate software requirements specification", "Verify SRS", "Confirm SRS by Customer", "Generate Traceability Record (SRS)"];
            $softwareDesign = ["Generate architectural design", "Generate Detailed Design", "Design Test cases & Test procedures", "Verify Software design", "Generate Traceability Record (UI & TC)"];
            $construction = ["Develop: Login function", "Develop: CRUD customer registration form function", "Develop: Sync DBD function", "Develop: Approve,Disapprove,Suspending function", "Develop: Enter Customer code function", "Generate Traceability Record (Component)", "Verify Traceability Record"];
            $testing = ["Generate Test Report (Internal Testing)", "Generate user manual (Software User Document)", "Verify Software User Document", "Training", "UAT"];
            $delivery = ["Generate Product Operation Guide (Install manual)", "Generate Maintenance Documents (MA)", "Verify Install manual & MA", "Generate Baseline Version (Software Configuration, V&V)", "Deploy software to production environment"];
            $projectClosure = ["Generate Acceptance Record", "Generate tax invoice", "Check Software Configuration, V&V", "Send deliverables"];
            // $riskManagement = ["Risk monitoring"];
            // return response()->json([$projectPlaning,$softwareAnalysis,$softwareDesign,$construction,$testing,$delivery,$projectClosure]);

            if (!is_null($projectPlaning) && !is_null($softwareAnalysis) && !is_null($softwareDesign) && !is_null($construction) && !is_null($testing) && !is_null($delivery) && !is_null($projectClosure)) {
                foreach ($projectPlaning as $valuePP) {
                    ## Add projectPlaning template
                    $templatePP = [
                        "project_id"    => $this->MongoDBObjectId($projectID),
                        "teamspace_id"  => $this->MongoDBObjectId($teamspaceID),
                        "issue_name"    => $valuePP, // Using $value instead of $projectPlaning
                        "description"   => "",
                        "iso_process"   => "PROJECT_PLANNING",
                        "difficulty_level" => "",
                        "status_group"  => "backlog",
                        "status"        => "Backlog",
                        "assigned"      => [],
                        "priority"      => "none",
                        "labels"        => [],
                        "tags"            => "",
                        "require_check_by" => "",
                        "comments"      => [],
                        "link"          => [],
                        "start_date"    => $startDate,
                        "end_date"      => $startDate,
                        "is_approved"   => null,
                        "created_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection("ProjectsIssue")->insertOne($templatePP);
                }

                foreach ($softwareAnalysis as $valueSA) {
                    ## Add softwareAnalysis template
                    $templateSA = [
                        "project_id"    => $this->MongoDBObjectId($projectID),
                        "teamspace_id"  => $this->MongoDBObjectId($teamspaceID),
                        "issue_name"    => $valueSA, // Using $value instead of $softwareAnalysis
                        "description"   => "",
                        "iso_process"   => "SOFTWARE_ANALYSIS",
                        "difficulty_level" => "",
                        "status_group"  => "backlog",
                        "status"        => "Backlog",
                        "assigned"      => [],
                        "priority"      => "none",
                        "labels"        => [],
                        "tags"            => "",
                        "require_check_by" => "",
                        "comments"      => [],
                        "link"          => [],
                        "start_date"    => $startDate,
                        "end_date"      => $startDate,
                        "is_approved"   => null,
                        "created_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection("ProjectsIssue")->insertOne($templateSA);
                }

                foreach ($softwareDesign as $valueSD) {
                    ## Add softwareDesign template
                    $templateSD = [
                        "project_id"    => $this->MongoDBObjectId($projectID),
                        "teamspace_id"  => $this->MongoDBObjectId($teamspaceID),
                        "issue_name"    => $valueSD, // Using $value instead of $softwareDesign
                        "description"   => "",
                        "iso_process"   => "SOFTWARE_DESIGN",
                        "difficulty_level" => "",
                        "status_group"  => "backlog",
                        "status"        => "Backlog",
                        "assigned"      => [],
                        "priority"      => "none",
                        "labels"        => [],
                        "tags"            => "",
                        "require_check_by" => "",
                        "comments"      => [],
                        "link"          => [],
                        "start_date"    => $startDate,
                        "end_date"      => $startDate,
                        "is_approved"   => null,
                        "created_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection("ProjectsIssue")->insertOne($templateSD);
                }

                foreach ($construction as $valueC) {
                    ## Add construction template
                    $templateC = [
                        "project_id"    => $this->MongoDBObjectId($projectID),
                        "teamspace_id"  => $this->MongoDBObjectId($teamspaceID),
                        "issue_name"    => $valueC, // Using $value instead of $construction
                        "description"   => "",
                        "iso_process"   => "CONSTRUCTION",
                        "difficulty_level" => "",
                        "status_group"  => "backlog",
                        "status"        => "Backlog",
                        "assigned"      => [],
                        "priority"      => "none",
                        "labels"        => [],
                        "tags"            => "",
                        "require_check_by" => "",
                        "comments"      => [],
                        "link"          => [],
                        "start_date"    => $startDate,
                        "end_date"      => $startDate,
                        "is_approved"   => null,
                        "created_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection("ProjectsIssue")->insertOne($templateC);
                }

                foreach ($testing as $valueT) {
                    ## Add testing template
                    $templateT = [
                        "project_id"    => $this->MongoDBObjectId($projectID),
                        "teamspace_id"  => $this->MongoDBObjectId($teamspaceID),
                        "issue_name"    => $valueT, // Using $value instead of $testing
                        "description"   => "",
                        "iso_process"   => "TESTING",
                        "difficulty_level" => "",
                        "status_group"  => "backlog",
                        "status"        => "Backlog",
                        "assigned"      => [],
                        "priority"      => "none",
                        "labels"        => [],
                        "tags"            => "",
                        "require_check_by" => "",
                        "comments"      => [],
                        "link"          => [],
                        "start_date"    => $startDate,
                        "end_date"      => $startDate,
                        "is_approved"   => null,
                        "created_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection("ProjectsIssue")->insertOne($templateT);
                }

                foreach ($delivery as $valueD) {
                    ## Add delivery template
                    $templateD = [
                        "project_id"    => $this->MongoDBObjectId($projectID),
                        "teamspace_id"  => $this->MongoDBObjectId($teamspaceID),
                        "issue_name"    => $valueD, // Using $value instead of $delivery
                        "description"   => "",
                        "iso_process"   => "DELIVERY",
                        "difficulty_level" => "",
                        "status_group"  => "backlog",
                        "status"        => "Backlog",
                        "assigned"      => [],
                        "priority"      => "none",
                        "labels"        => [],
                        "tags"            => "",
                        "require_check_by" => "",
                        "comments"      => [],
                        "link"          => [],
                        "start_date"    => $startDate,
                        "end_date"      => $startDate,
                        "is_approved"   => null,
                        "created_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection("ProjectsIssue")->insertOne($templateD);
                }

                foreach ($projectClosure as $valuePC) {
                    ## Add projectClosure template
                    $templatePC = [
                        "project_id"    => $this->MongoDBObjectId($projectID),
                        "teamspace_id"  => $this->MongoDBObjectId($teamspaceID),
                        "issue_name"    => $valuePC, // Using $value instead of $projectClosure
                        "description"   => "",
                        "iso_process"   => "PROJECT_CLOSURE",
                        "difficulty_level" => "",
                        "status_group"  => "backlog",
                        "status"        => "Backlog",
                        "assigned"      => [],
                        "priority"      => "none",
                        "labels"        => [],
                        "tags"            => "",
                        "require_check_by" => "",
                        "comments"      => [],
                        "link"          => [],
                        "start_date"    => $startDate,
                        "end_date"      => $startDate,
                        "is_approved"   => null,
                        "created_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection("ProjectsIssue")->insertOne($templatePC);
                }

                // foreach ($riskManagement as $valueRM) {
                //     ## Add riskManagement template
                //     $templateRM = [
                //         "project_id"    => $this->MongoDBObjectId($projectID),
                //         "teamspace_id"  => $this->MongoDBObjectId($teamspaceID),
                //         "issue_name"    => $valueRM, // Using $value instead of $riskManagement
                //         "description"   => "",
                //         "iso_process"   => "RISK_MANAGEMENT",
                //         "difficulty_level" => "",
                // "status_group"  => "backlog",
                // "status"        => "Backlog",
                // "assigned"      => [],
                // "priority"      => "none",
                //         "labels"        => [],
                //         "tags"            => "",
                //         "require_check_by" => "",
                //         "comments"      => [],
                //         "link"          => [],
                //         "start_date"    => $startDate,
                //         "end_date"      => $startDate,
                //         "is_approved"   => null,
                //         "created_at"    => $timestamp,
                //     ];
                //     $result = $this->db->selectCollection("ProjectsIssue")->insertOne($templateRM);
                // }
                return response()->json([
                    "status" => "success",
                    "message" => "Add template successfully !!",
                    "data" => $result // Returning $result outside of the loop
                ], 200);
            }
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //? [GET] /task/get-task-by-user
    public function getTaskByUser(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            ##main issue
            $pipeline1 =  [
                // ['$match' => ['parent_id' => null]],
                // ['$project' => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'project_id' => 1, 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$cond' => ['if' => ['$eq' => [['$type' => '$$resp.comment_at'], 'date']], 'then' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'else' => '$$resp.comment_at']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                // ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'project_desc']],
                // ['$project' => ['_id' => 0, 'project_id' => 1, 'issue_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'assigned' => 1, 'projecct_type_id' => ['$arrayElemAt' => ['$project_desc.project_type_id', 0]]]],
                // ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'projecct_type_id', 'foreignField' => '_id', 'as' => 'project_type_decs']],
                // ['$project' => ['issue_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => ['$toString' => '$projecct_type_id'], 'cost_estimation' => ['$arrayElemAt' => ['$project_type_decs.cost_estimation', 0]], 'project_type' => ['$arrayElemAt' => ['$project_type_decs.project_type', 0]]]],
                // ['$project' => ['issue_id' => 1, 'project_id' => 1, 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => 1, 'project_type' => 1, 'total_estimation' => ['$sum' => [['$arrayElemAt' => ['$cost_estimation.raw_materials', 0]], ['$arrayElemAt' => ['$cost_estimation.direct_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.overhead_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.gross_profit', 0]]]], 'currency' => ['$arrayElemAt' => ['$cost_estimation.currency', 0]], 'weight_score' => ['$switch' => ['branches' => [['case' => ['$eq' => ['$difficulty_level', 'NEW_COMER']], 'then' => 1], ['case' => ['$eq' => ['$difficulty_level', 'BEGINNER']], 'then' => 2.5], ['case' => ['$eq' => ['$difficulty_level', 'INTERMEDIATE']], 'then' => 5], ['case' => ['$eq' => ['$difficulty_level', 'ADVANCED']], 'then' => 7.5], ['case' => ['$eq' => ['$difficulty_level', 'EXPERT']], 'then' => 10]], 'default' => null]]]],
                // ['$group' => ['_id' => '$project_id', 'elems' => ['$push' => '$$ROOT'], 'sum_weight_by_project' => ['$sum' => '$weight_score']]],
                // ['$unwind' => '$elems'],
                // ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$elems', '$$ROOT']]]],
                // ['$project' => ['_id' => 0, 'elems' => 0]],
                // ['$addFields' => ['task_cost' => ['$divide' => [['$multiply' => ['$total_estimation', '$weight_score']], '$sum_weight_by_project']]]],
                // ['$unwind' => '$assigned'],
                // ['$group' => ['_id' => '$assigned', 'issues' => ['$push' => ['issue_name' => '$issue_name', 'description' => '$description', 'iso_process' => '$iso_process', 'difficulty_level' => '$difficulty_level', 'status_group' => '$status_group', 'status' => '$status', 'priority' => '$priority', 'labels' => '$labels', 'tags' => '$tags', 'link' => '$link', 'start_date' => '$start_date', 'end_date' => '$end_date', 'issue_id' => 'issue_id', 'parent_id' => '$parent_id', 'require_check_by' => '$require_check_by', 'comments' => '$comments', 'create_by' => '$create_by', 'day_remain' => '$day_remain', 'created_at' => '$created_at', 'updated_at' => 'updated_at', 'project_id' => '$project_id', 'projecct_type_id' => '$projecct_type_id', 'project_type' => '$project_type', 'total_estimation' => '$total_estimation', 'currency' => '$currency', 'weight_score' => '$weight_score', 'sum_weight_by_project' => '$sum_weight_by_project', 'task_cost' => '$task_cost']]]],
                // ['$project' => ['_id' => 0, 'user_id' => ['$toObjectId' => '$_id'], 'issues' => 1]],
                // ['$lookup' => ['from' => 'Users', 'localField' => 'user_id', 'foreignField' => '_id', 'as' => 'user']],
                // ['$project' => ['_id' => 0, 'issues' => 1, 'user_id' => ['$toString' => '$user_id'], 'name' => ['$arrayElemAt' => ['$user.name', 0]]]],

                ['$match' => ['parent_id' => null]],
                ['$project' => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'project_id' => 1, 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$cond' => ['if' => ['$eq' => [['$type' => '$$resp.comment_at'], 'date']], 'then' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'else' => '$$resp.comment_at']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'project_desc']],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'issue_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'assigned' => 1, 'projecct_type_id' => ['$arrayElemAt' => ['$project_desc.project_type_id', 0]]]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'project_planing', 'pipeline' => [['$project' => ['_id' => 0, 'project_planing_id' => '$_id', 'selling_prices' => '$selling_prices']]]]],
                ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'projecct_type_id', 'foreignField' => '_id', 'as' => 'project_type_decs']],
                ['$project' => ['issue_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => ['$toString' => '$projecct_type_id'], 'project_type' => ['$arrayElemAt' => ['$project_type_decs.project_type', 0]], 'selling_prices' => ['$arrayElemAt' => ['$project_planing.selling_prices', 0]], 'project_planing_id' => ['$arrayElemAt' => ['$project_planing.project_planing_id', 0]]]],
                ['$project' => ['issue_id' => 1, 'project_id' => 1, 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'projecct_type_id' => 1, 'project_type' => 1, 'selling_prices' => 1, 'project_planing_id' => ['$toString' => '$project_planing_id'], 'weight_score' => ['$switch' => ['branches' => [['case' => ['$eq' => ['$difficulty_level', 'NEW_COMER']], 'then' => 2], ['case' => ['$eq' => ['$difficulty_level', 'BEGINNER']], 'then' => 4], ['case' => ['$eq' => ['$difficulty_level', 'INTERMEDIATE']], 'then' => 6], ['case' => ['$eq' => ['$difficulty_level', 'ADVANCED']], 'then' => 8], ['case' => ['$eq' => ['$difficulty_level', 'EXPERT']], 'then' => 10]], 'default' => null]]]],
                ['$group' => ['_id' => '$project_id', 'elems' => ['$push' => '$$ROOT'], 'sum_weight_by_project' => ['$sum' => '$weight_score']]],
                ['$unwind' => '$elems'],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$elems', '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'elems' => 0]],
                ['$addFields' => ['task_cost' => ['$divide' => [['$multiply' => ['$selling_prices', '$weight_score']], '$sum_weight_by_project']]]],
                ['$unwind' => '$assigned'],
                ['$group' => ['_id' => '$assigned', 'issues' => ['$push' => ['issue_name' => '$issue_name', 'description' => '$description', 'iso_process' => '$iso_process', 'difficulty_level' => '$difficulty_level', 'status_group' => '$status_group', 'status' => '$status', 'priority' => '$priority', 'labels' => '$labels', 'tags' => '$tags', 'link' => '$link', 'start_date' => '$start_date', 'end_date' => '$end_date', 'issue_id' => 'issue_id', 'parent_id' => '$parent_id', 'require_check_by' => '$require_check_by', 'comments' => '$comments', 'create_by' => '$create_by', 'day_remain' => '$day_remain', 'created_at' => '$created_at', 'updated_at' => 'updated_at', 'project_id' => '$project_id', 'projecct_type_id' => '$projecct_type_id', 'project_type' => '$project_type', 'total_estimation' => '$total_estimation', 'currency' => '$currency', 'weight_score' => '$weight_score', 'project_planing_id' => '$project_planing_id', 'selling_prices' => '$selling_prices', 'task_cost' => '$task_cost']]]],
                ['$project' => ['_id' => 0, 'user_id' => ['$toObjectId' => '$_id'], 'issues' => 1]],
                ['$lookup' => ['from' => 'Users', 'localField' => 'user_id', 'foreignField' => '_id', 'as' => 'user']],
                ['$project' => ['_id' => 0, 'issues' => 1, 'user_id' => ['$toString' => '$user_id'], 'name' => ['$arrayElemAt' => ['$user.name', 0]]]]
            ];

            $result1 = $this->db->selectCollection('ProjectsIssue')->aggregate($pipeline1);

            ##sub issue
            $pipeline2 =  [
                ['$match' => ['parent_id' => ['$ne' => null]]],
                ['$project' => ['_id' => 0, 'issue_id' => ['$toString' => '$_id'], 'project_id' => 1, 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$cond' => ['if' => ['$eq' => [['$type' => '$$resp.comment_at'], 'date']], 'then' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'else' => '$$resp.comment_at']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'project_desc']],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'issue_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'assigned' => 1, 'projecct_type_id' => ['$arrayElemAt' => ['$project_desc.project_type_id', 0]]]],
                ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'projecct_type_id', 'foreignField' => '_id', 'as' => 'project_type_decs']],
                ['$project' => ['issue_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => 1, 'parent_id' => 1, 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'comments' => 1, 'start_date' => 1, 'end_date' => 1, 'create_by' => 1, 'day_remain' => 1, 'created_at' => 1, 'updated_at' => 1, 'assigned' => 1, 'projecct_type_id' => ['$toString' => '$projecct_type_id'], 'project_type' => ['$arrayElemAt' => ['$project_type_decs.project_type', 0]]]],
                ['$unwind' => '$assigned'],
                ['$group' => ['_id' => '$assigned', 'issues' => ['$push' => ['issue_name' => '$issue_name', 'description' => '$description', 'iso_process' => '$iso_process', 'difficulty_level' => '$difficulty_level', 'status_group' => '$status_group', 'status' => '$status', 'priority' => '$priority', 'labels' => '$labels', 'tags' => '$tags', 'link' => '$link', 'start_date' => '$start_date', 'end_date' => '$end_date', 'issue_id' => 'issue_id', 'parent_id' => '$parent_id', 'require_check_by' => '$require_check_by', 'comments' => '$comments', 'create_by' => '$create_by', 'day_remain' => '$day_remain', 'created_at' => '$created_at', 'updated_at' => 'updated_at', 'project_id' => '$project_id', 'projecct_type_id' => '$projecct_type_id', 'project_type' => '$project_type']]]],
                ['$project' => ['_id' => 0, 'user_id' => ['$toObjectId' => '$_id'], 'issues' => 1]],
                ['$lookup' => ['from' => 'Users', 'localField' => 'user_id', 'foreignField' => '_id', 'as' => 'user']],
                ['$project' => ['_id' => 0, 'issues' => 1, 'user_id' => ['$toString' => '$user_id'], 'name' => ['$arrayElemAt' => ['$user.name', 0]]]]
            ];

            $result2 = $this->db->selectCollection('ProjectsIssue')->aggregate($pipeline2);

            $array1 = array();
            foreach ($result1 as $doc) \array_push($array1, $doc);

            $array2 = array();
            foreach ($result2 as $doc) \array_push($array2, $doc);

            $result = array_merge($array1, $array2);

            return response()->json([
                "status" => "success",
                "message" => "Get all issue by user successfully !!",
                "data" => $result
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
