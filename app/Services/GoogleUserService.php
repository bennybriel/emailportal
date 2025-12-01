<?php

namespace App\Services;

use Google\Client;
use Google\Service\Directory;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Directory\User;

class GoogleUserService
{
    protected Directory $service;

    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(storage_path('app/google/emailauth.json'));
        $client->addScope('https://www.googleapis.com/auth/admin.directory.user');
        $client->setSubject('webmaster@lautech.edu.ng');

        $this->service = new Directory($client); // keep only this line
    }
    public function checkIfEmailExists(string $email): bool
    {
        try {
            $this->service->users->get($email);
            return true; // user exists
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 404) {
                return false; // user not found
            }
            throw $e; // re-throw other errors
        }
    }

    private function generatePassword(): string
    {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $num   = '0123456789';
        $spec  = '!@#$%^&*';

        $base = $upper.$lower.$num.$spec;

        $pwd = $upper[rand(0,25)]
             . $lower[rand(0,25)]
             . $num[rand(0,9)]
             . $spec[rand(0,7)];

        for ($i = strlen($pwd); $i < 12; $i++) {
            $pwd .= $base[rand(0, strlen($base)-1)];
        }

        return str_shuffle($pwd);
    }

public function createUser(array $data): array
{
    try {
        // Required fields
        $firstname = $data['firstname'] ?? null;
        $lastname  = $data['lastname'] ?? null;
        $othername = $data['othername'] ?? '';
        $domain    = $data['domain'] ?? null;
        $password  = $data['password'] ?? bin2hex(random_bytes(10));
        $matricno  = $data['matricno'] ?? null;
        $programme = $data['programme'] ?? null;
        $session   = $data['session'] ?? null;
        
        //Log payload
           if (!$firstname || !$lastname || !$domain) {
            return [
                'success' => false,
                'error' => 'Missing required fields: firstname, lastname, domain'
            ];
        }

        // ❗ STEP 1: Check if matricno already exists
        if (!empty($matricno)) {
            $query = "externalId:${matricno}";
            $existing = $this->service->users->listUsers([
                'customer' => 'my_customer',
                'query'    => $query,
            ]);

            if (!empty($existing->getUsers())) {
                $existingUser = $existing->getUsers()[0];
                return [
                    'success' => false,
                    'error'   => "Matric number '${matricno}' already belongs to {$existingUser->getPrimaryEmail()}",
                ];
            }
        }

        // STEP 2: Generate base local part: f + o + lastname
        $baseLocal = strtolower(
            substr($firstname, 0, 1) .
            ($othername ? substr($othername, 0, 1) : '') .
            $lastname
        );

        $domainPart = ltrim($domain, '@');
        $primaryEmail = $baseLocal . '@' . $domainPart;

        // STEP 3: Resolve duplicate emails: add 01, 02...
        $finalEmail = $primaryEmail;
        $counter = 1;

        while ($this->checkIfEmailExists($finalEmail)) {
            $finalEmail = $baseLocal . sprintf('%02d', $counter) . '@' . $domainPart;
            $counter++;

            if ($counter > 99) {
                return [
                    'success' => false,
                    'error' => 'Too many duplicate usernames'
                ];
            }
        }

        // STEP 4: Build externalIds (matricno, session, programme)
        $externalIds = [];

        if (!empty($matricno)) {
            $externalIds[] = [
                'type' => 'custom',
                'customType' => 'matricno',
                'value' => $matricno
            ];
        }

        if (!empty($session)) {
            $externalIds[] = [
                'type' => 'custom',
                'customType' => 'session',
                'value' => $session
            ];
        }

        if (!empty($programme)) {
            $externalIds[] = [
                'type' => 'custom',
                'customType' => 'programme',
                'value' => $programme
            ];
        }

        // STEP 5: Build Google User object
        $user = new User([
            'primaryEmail' => $finalEmail,
            'name' => [
                'givenName'  => $firstname,
                'familyName' => $lastname,
                'middleName' => $othername,
            ],
            'password' => $password,
            'changePasswordAtNextLogin' => true,
            'hashFunction' => 'crypt',
            'externalIds' => $externalIds
        ]);

        // STEP 6: Insert user in Google Workspace
        $response = $this->service->users->insert($user);
        $this->insertEmailInfo($domain, $firstname, $lastname, $othername, $finalEmail, $password, $matricno,$matric,$session,$programme);

        $this->insertEmailLogger($data,$response);
        return [
            'success' => true,
            'message' => 'User created successfully',
            'email' => $finalEmail,
            'password' => $password
        ];

    } catch (GoogleServiceException $e) {
        return [
            'success' => false,
            'error'   => $e->getMessage()
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error'   => 'Unexpected error: ' . $e->getMessage()
        ];
    }
}

public function resetPassword(string $email, string $password): array
{
    try {
        // Fetch user (Google throws 404 if not found)
        $user = $this->service->users->get($email);
        // Update password
        $user->setPassword($password);
        $user->setChangePasswordAtNextLogin(true);
        //$user->setHashFunction('crypt');
        $this->service->users->update($email, $user);

        return [
            'success' => true,
            'message' => 'Password reset successfully',
            'password' => $password,
        ];

    } catch (GoogleServiceException $e) {

        // Decode Google error JSON safely
        $decodedError = json_decode($e->getMessage(), true);

        // Default message if decoding fails
        $cleanMessage = 'An error occurred while resetting the password.';

        if (isset($decodedError['error']['message'])) {
            $cleanMessage = $decodedError['error']['message'];  // e.g., "Invalid Password"
        }

        // Handle user-not-found explicitly
        if ($e->getCode() === 404) {
            return [
                'success' => false,
                'error_code' => 404,
                'message' => "User '{$email}' not found",
            ];
        }

        return [
            'success' => false,
            'error_code' => $e->getCode(),
            'message' => $cleanMessage,
        ];

    } catch (\Exception $e) {
        // Unexpected error
        return [
            'success' => false,
            'error_code' => 500,
            'message' => 'Unexpected server error: ' . $e->getMessage(),
        ];
    }
}


public function getUser(string $email): array
{
    try {
        $user = $this->service->users->get($email);
        $userName = $user->getName();

        // Initialize fields
        $matricno = null;
        $programme = null;
        $session = null;

        // Extract data from externalIds if they exist
        if ($user->getExternalIds()) {
            foreach ($user->getExternalIds() as $externalId) {
                $type = $externalId['type'] ?? '';
                $customType = $externalId['customType'] ?? '';
                $value = $externalId['value'] ?? null;

                if ($type === 'custom') {
                    switch ($customType) {
                        case 'matricno':
                            $matricno = $value;
                            break;
                        case 'programme':
                            $programme = $value;
                            break;
                        case 'session':
                            $session = $value;
                            break;
                    }
                }
            }
        }

        return [
            'success' => true,
            'user' => [
                'primaryEmail' => $user->getPrimaryEmail(),
                'givenName'    => $userName->givenName ?? '',
                'familyName'   => $userName->familyName ?? '',
                'middleName'   => $userName->middleName ?? '',
                'id'           => $user->getId(),
                'orgUnitPath'  => $user->getOrgUnitPath(),
                'suspended'    => $user->getSuspended(),
                'lastLoginTime'=> $user->getLastLoginTime(),
                'matricno'     => $matricno,
                'programme'    => $programme,
                'session'      => $session,
            ]
        ];

    } catch (GoogleServiceException $e) {
        if ($e->getCode() === 404) {
            return [
                'success' => false,
                'message' => "User '{$email}' not found",
            ];
        }

        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

public function getUserByMatricno(string $matricno): array
{
    try {
        // Search for user whose externalId matches the matricno
        $query = "externalId:{$matricno}";
        
        $usersList = $this->service->users->listUsers([
            'customer'   => 'my_customer',
            'query'      => $query,
            'maxResults' => 1
        ]);

        $users = $usersList->getUsers();

        if (empty($users)) {
            return [
                'success' => false,
                'message' => "No user found with matricno '{$matricno}'",
            ];
        }

        $user = $users[0];
        $userName = $user->getName();

        // Extract custom external IDs
        $programme = null;
        $session   = null;
        $matric_no = $matricno;

        if ($user->getExternalIds()) {
            foreach ($user->getExternalIds() as $ext) {
                if (($ext['type'] ?? '') === 'custom') {
                    switch ($ext['customType']) {
                        case 'matricno':
                            $matric_no = $ext['value'];
                            break;
                        case 'programme':
                            $programme = $ext['value'];
                            break;
                        case 'session':
                            $session = $ext['value'];
                            break;
                    }
                }
            }
        }

        return [
            'success' => true,
            'user' => [
                'primaryEmail'  => $user->getPrimaryEmail(),
                'givenName'     => $userName->givenName ?? '',
                'familyName'    => $userName->familyName ?? '',
                'middleName'    => $userName->middleName ?? '',
                'id'            => $user->getId(),
                'orgUnitPath'   => $user->getOrgUnitPath(),
                'suspended'     => $user->getSuspended(),
                'lastLoginTime' => $user->getLastLoginTime(),
                'matricno'      => $matric_no,
                'programme'     => $programme,
                'session'       => $session,
            ]
        ];

    } catch (GoogleServiceException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

public function addMatricnoToUser(string $email, string $matricno, string $programme, string $session): array
{
      ini_set('max_execution_time', 600);
    try {
        // STEP 1: Check if matricno already belongs to another user
        $query = "externalId:${matricno}";
        $existingUsers = $this->service->users->listUsers([
            'customer' => 'my_customer',
            'query'    => $query,
        ]);

        $matched = $existingUsers->getUsers();

        if (!empty($matched)) {
            $foundUser = $matched[0];

            // If matricno belongs to a different user, block it
            if (strtolower($foundUser->getPrimaryEmail()) !== strtolower($email)) {
                return [
                    'success' => false,
                    'message' => "Matric number '{$matricno}' already belongs to another user: {$foundUser->getPrimaryEmail()}",
                ];
            }
        }

        // STEP 2: Proceed to update this user's profile
        $user = $this->service->users->get($email);
        if (!$user) {
            return [
                'success' => false,
                'message' => "User '{$email}' not found",
            ];
        }

        $externalIds = $user->getExternalIds() ?? [];

        $matricUpdated = false;
        $programmeUpdated = false;
        $sessionUpdated = false;

        foreach ($externalIds as &$ext) {
            if (($ext['type'] ?? '') === 'custom') {
                switch ($ext['customType'] ?? '') {
                    case 'matricno':
                        $ext['value'] = $matricno;
                        $matricUpdated = true;
                        break;

                    case 'programme':
                        $ext['value'] = $programme;
                        $programmeUpdated = true;
                        break;

                    case 'session':
                        $ext['value'] = $session;
                        $sessionUpdated = true;
                        break;
                }
            }
        }
        unset($ext);

        // Add missing ones
        if (!$matricUpdated) {
            $externalIds[] = [
                'type' => 'custom',
                'customType' => 'matricno',
                'value' => $matricno
            ];
        }
        if (!$programmeUpdated) {
            $externalIds[] = [
                'type' => 'custom',
                'customType' => 'programme',
                'value' => $programme
            ];
        }
        if (!$sessionUpdated) {
            $externalIds[] = [
                'type' => 'custom',
                'customType' => 'session',
                'value' => $session
            ];
        }

        // Save updates
        $user->setExternalIds($externalIds);
        $this->service->users->update($email, $user);

        return [
            'success' => true,
            'message' => "Matricno, programme and session updated for '{$email}'",
        ];

        //$this->insertEmailLogger()

    } catch (GoogleServiceException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

public function insertEmailInfo($domain, $firstname, $lastname, $othername, $email, $password, $userid,$matric,$session,$programme)
{
    return DB::table('email_info')->insertGetId([
        'domain'     => $domain,
        'firstname'  => $firstname,
        'lastname'   => $lastname,
        'othername'  => $othername,
        'email'      => $email,
        'password'   => $password,
        'userid'     => $userid,
        'status'     => 1,
        'created_at' => now(),
        'matric'  =>$matric,
        'session'=>$session,
        'programme'=>$programme
    ]);
}

public function insertEmailLogger($payload, $response)
{
    return DB::table('email_logger')->insertGetId([
        'payload'    => $payload,
        'response'   => $response,
        'created_at' => now(),
    ]);
}

public function deleteGoogleUser($email)
{
    try {
       

        // Delete user
        $this->service->users->delete($email);

        return [
            'success' => true,
            'message' => "Google Workspace account for {$email} deleted successfully."
        ];

    } catch (\Google_Service_Exception $e) {

        $error = json_decode($e->getMessage(), true);

        return [
            'success' => false,
            'error_code' => $error['error']['code'] ?? null,
            'error_message' => $error['error']['message'] ?? 'Unknown error'
        ];

    } catch (\Exception $e) {

        return [
            'success' => false,
            'error_message' => $e->getMessage()
        ];
    }
}

public function getTotalUsers(): int
{
    $usersList = $this->service->users->listUsers(['customer' => 'my_customer']);
    return count($usersList->getUsers());
}

public function getTotalUsersBySession(string $session): int
{
    // query custom field 'session'
    $query = "externalId.type:custom externalId.customType:session externalId.value:{$session}";
    $usersList = $this->service->users->listUsers([
        'customer' => 'my_customer',
        'query' => $query
    ]);

    return count($usersList->getUsers());
}

public function getTotalUsersByProgramme(string $programme): int
{
    $query = "externalId.type:custom externalId.customType:programme externalId.value:{$programme}";
    $usersList = $this->service->users->listUsers([
        'customer' => 'my_customer',
        'query' => $query
    ]);

    return count($usersList->getUsers());
}

}