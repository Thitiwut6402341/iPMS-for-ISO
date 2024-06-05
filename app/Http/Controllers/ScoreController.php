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

class ScoreController extends Controller
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


    //* [GET] /score/get-conclusion-all
    public function getConclusionAll(Request $request)
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
            // return response()->json($decoded);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d'); //date('Y-m-d H:i:s');


            $pipeline = [
                ['$project' => ['name' => 1, 'emp' => ['$toString' => '$_id']]],
                ['$lookup' => ['from' => 'ProjectsIssue', 'localField' => '_id', 'foreignField' => 'assigned', 'as' => 'result', 'pipeline' => [['$project' => ['_id' => 0, 'project_issue_id' => ['$toString' => '$_id'], 'is_approved' => 1, 'end_date' => 1]]]]],
                ['$unwind' => '$result'], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$result', '$$ROOT']]]],
                ['$group' => ['_id' => '$emp', 'name' => ['$first' => '$name'], 'approvedProjects' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', true]], 1, 0]]], 'totalProjectsPerson' => ['$sum' => 1]]],
                ['$addFields' => ['percentageProgress' => ['$multiply' => [['$divide' => ['$approvedProjects', '$totalProjectsPerson']], 100]]]],
                ['$project' => ['_id' => 1, 'name_en' => 1, 'percentageProgress' => 1, 'totalProjectsPerson' => 1, 'approvedProjects' => 1]]
            ];
            $result = $this->db->selectCollection('Users')->aggregate($pipeline);
            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);
            //    return response()->json($data);

            //! Team progress
            $pipeline2 = [
                ['$project' => ['_id' => 1, 'project_id' => 1, 'assigned' => 1, 'is_approved' => 1]],
                ['$match' => ['assigned' => ['$exists' => true, '$ne' => []]]], ['$group' => ['_id' => null, 'countAllTask' => ['$sum' => 1], 'countTrue' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', true]], 1, 0]]]]], ['$project' => ['_id' => 0]],
                ['$addFields' => ['TeamProgress' => ['$multiply' => [['$divide' => ['$countTrue', '$countAllTask']], 100]]]],
                ['$project' => ['TeamProgress' => 1]]
            ];
            $result2 = $this->db->selectCollection('ProjectsIssue')->aggregate($pipeline2);

            $data2 = array();
            foreach ($result2 as $doc2) \array_push($data2, $doc2);
            // return response()->json($data2);


            $combineData = [];

            //! Task management  all task assigned by person
            $pipeline3 = [
                ['$project' => ['_id' => 1, 'end_date' => 1, 'project_id' => 1, 'is_approved' => 1, 'assigned' => 1]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'assigned', 'foreignField' => 'user_id', 'as' => 'result', 'pipeline' =>
                [['$project' => ['name' => ['$toString' => '$name_en'], '_id' => 0, 'user_id' => ['$toString' => '$user_id']]]]]],
                ['$unwind' => '$result'], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$result', '$$ROOT']]]],
                ['$group' => ['_id' => '$user_id', 'countAllTaskPerson' => ['$sum' => 1]]],
                ['$project' => ['user_id' => '$_id', '_id' => 0, 'countAllTaskPerson' => 1]]
            ];

            $result3 = $this->db->selectCollection('ProjectsIssue')->aggregate($pipeline3);
            $data3 = array();
            foreach ($result3 as $doc3) \array_push($data3, $doc3);
            // return response()->json($data);

            $pipeline5 = [
                ['$project' => ['_id' => 1, 'end_date' => 1, 'project_id' => 1, 'is_approved' => 1, 'assigned' => 1]],
                ['$match' => ['is_approved' => ['$ne' => true], 'assigned' => ['$exists' => true, '$ne' => []]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'assigned', 'foreignField' => 'user_id', 'as' => 'result', 'pipeline' => [['$project' => ['name' => ['$toString' => '$name_en'], '_id' => 0, 'user_id' => ['$toString' => '$user_id']]]]]],
                ['$unwind' => '$result'],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$result', '$$ROOT']]]],
                ['$project' => ['result' => 0]],
                ['$group' => ['_id' => '$user_id', 'countNotFinishTask' => ['$sum' => 1], 'overDue' => ['$sum' => ['$cond' => [['$lt' => ['$end_date', $date]], 1, 0]]], 'name' => ['$last' => '$name']]],
                ['$project' => ['user_id' => '$_id', '_id' => 0, 'countNotFinishTask' => 1, 'overDue' => 1, 'name_en' => '$name']]
            ];

            $result5 = $this->db->selectCollection('ProjectsIssue')->aggregate($pipeline5);
            $data5 = array();
            foreach ($result5 as $doc5) \array_push($data5, $doc5);

            // $calPercentageTimeManage = (1-($countDate/$countAllTask))*100;
            // return response()->json($calPercentageTimeManage);

            //? Data User no tast assign
            $pipeline4 = [['$project' => ['user_id' => ['$toString' => '$_id'], '_id' => 0, 'name_en' => '$name']]];
            $result4 = $this->db->selectCollection('Users')->aggregate($pipeline4);
            $data4 = array();
            foreach ($result4 as $doc4) \array_push($data4, $doc4);

            // return response()->json($data);
            // return response()->json($data4[1]['name']);

            foreach ($data4 as $doc4) {
                $nameExists = false;
                foreach ($combineData as $combinedDoc) {
                    if ($doc4['name_en'] == $combinedDoc['name_en']) {
                        $nameExists = true;
                        break;
                    }
                }
                if (!$nameExists) {
                    $doc4['personal_progress'] = 0;
                    $doc4['approved_project'] = 0;
                    if (!empty($data2[0]['TeamProgress'])) {
                        $doc4['team_progress'] = $data2[0]['TeamProgress'];
                    } else {
                        $doc4['team_progress'] = 0;
                    }

                    $doc4['time_performance'] = 100;
                    $doc4['over_due'] = 0;
                    $doc4['overall_topic'] = 0;
                    $combineData[] = $doc4;
                }
            }
            //   return response()->json($data5);

            foreach ($data5 as $doc5) {
                $nameExists = false;
                foreach ($combineData as $combinedDoc) {
                    if ($combinedDoc->user_id == $doc5->user_id) {
                        $combinedDoc->over_due = $doc5->overDue;
                    }
                }
            }

            foreach ($data3 as $doc3) {
                $nameExists = false;
                foreach ($combineData as $combinedDoc) {
                    if ($combinedDoc->user_id == $doc3->user_id) {
                        $combinedDoc->overall_topic = $doc3->countAllTaskPerson;
                    }
                }
            }
            foreach ($data as $doc) {
                $nameExists = false;
                foreach ($combineData as $combinedDoc) {
                    if ($combinedDoc->user_id == $doc->_id) {
                        $combinedDoc->personal_progress = $doc->percentageProgress;
                        $combinedDoc->approved_project = $doc->approvedProjects;
                    }
                }
            }

            foreach ($combineData as $value) {
                if ($value->overall_topic != 0) {
                    $value->time_performance = (1 - ($value->over_due / $value->overall_topic)) * 100;
                }
            }

            return response()->json(
                [
                    "status" => "success",
                    "message" => "Conclusion all",
                    "data" => $combineData
                ],
                200
            );
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }
}
