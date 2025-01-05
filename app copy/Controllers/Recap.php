<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Recap extends BaseController
{
    use ResponseTrait; // Include the ResponseTrait to use respond()

    protected $db, $builder;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->builder = $this->db->table('machine'); // Connect to the 'machine' table
    }

    public function index(): string
    {
        // Fetch all machine names from the 'machine' table
        $machines = $this->builder->select('MachineID')->get()->getResultArray();

        // Pass the machine names to the view
        $data = [
            'title' => 'Machine Recap',
            'sidebarData' => 'recap',
            'machines' => $machines
        ];

        return view('user/recap', $data);
    }

    public function fetchMachineData()
    {
        // Get POST data
        $input = $this->request->getJSON(true);
        $machineName = $input['machineName'] ?? '';
        $date = $input['date'] ?? '';

        // Query the machine history based on machineID and date
        $historyBuilder = $this->db->table('machinehistory1');
        $data = $historyBuilder->select('ArcOn, ArcOff')
            ->where('MachineID', $machineName)
            ->where('Date', $date)
            ->get()
            ->getResultArray();

        return $this->respond($data);
    }

    public function calculateUsagePercentage()
    {
        try {
            // Get JSON input data
            $input = $this->request->getJSON(true);

            // Validate required input data
            if (!isset($input['machineName'], $input['date'], $input['startTime'], $input['endTime'])) {
                throw new \Exception('Invalid input data');
            }

            $machineName = $input['machineName'];
            $date = $input['date'];
            $startTime = $input['startTime'];
            $endTime = $input['endTime'];

            $machineID = $machineName;

            // Step 2: Sum ArcTotal within the specified time range
            $historyBuilder = $this->db->table('machinehistory1');
            $result = $historyBuilder->select('SUM(TIME_TO_SEC(ArcTotal)) AS totalArcTimeInSeconds')
                ->where('MachineID', $machineID)
                ->where('Date', $date)
                ->where('ArcOn >=', $startTime)
                ->where('ArcOff <=', $endTime)
                ->get()
                ->getRow();

            $totalArcTimeInSeconds = (int)($result->totalArcTimeInSeconds ?? 0);

            // Step 3: Calculate the total seconds in the given time range
            $startDateTime = new \DateTime("$date $startTime");
            $endDateTime = new \DateTime("$date $endTime");
            $timeDifferenceInSeconds = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();

            // Validate time range
            if ($timeDifferenceInSeconds <= 0) {
                throw new \Exception('Invalid time range. End time must be after start time.');
            }

            // Step 4: Calculate the usage percentage
            $usagePercentage = ($totalArcTimeInSeconds / $timeDifferenceInSeconds) * 100;

            // Return JSON response with usage data
            return $this->respond([
                'totalArcTime' => $totalArcTimeInSeconds,
                'usagePercentage' => round($usagePercentage, 2) // Rounded to two decimal places
            ]);
        } catch (\Exception $e) {
            // Return error response if an exception occurs
            return $this->respond(['error' => $e->getMessage()], 400);
        }
    }

    public function allCharts()
    {
        $date = $this->request->getGet('date'); // Get the date from the query parameter

        // Pass the date to the view
        $data = [
            'title' => 'All Machine Charts',
            'sidebarData' => 'All Machine Chart',
            'date' => $date // Pass the date to the view
        ];

        return view('user/allChart', $data);
    }

    public function fetchChartData()
    {
        // Get the date from the request
        $input = $this->request->getJSON();
        $date = $input->date ?? ''; // Get the date from the JSON input

        // Check if the date is empty
        if (empty($date)) {
            return $this->response->setJSON(['error' => 'Date is required'])->setStatusCode(400);
        }

        // Prepare the SQL query to fetch data for all machines based on the provided date
        $sql = "SELECT m.MachineID, mh.ArcOn, mh.ArcOff 
            FROM machine m
            JOIN machinehistory1 mh ON m.MachineID = mh.MachineID
            WHERE mh.Date = ?";

        // Prepare the statement
        $stmt = $this->db->connID->prepare($sql);
        $stmt->bind_param("s", $date); // Bind the date parameter
        $stmt->execute();
        $result = $stmt->get_result();

        // Initialize an array to hold the data
        $data = [];

        // Fetch the results and structure them
        while ($row = $result->fetch_assoc()) {
            $data[$row['MachineID']][] = [
                'ArcOn' => $row['ArcOn'],
                'ArcOff' => $row['ArcOff']
            ];
        }

        // Close the statement
        $stmt->close();

        // Include the received date in the response
        $response = [
            'date' => $date,
            'data' => $data
        ];

        // Return the response as JSON
        return $this->response->setJSON($response);
    }
}
