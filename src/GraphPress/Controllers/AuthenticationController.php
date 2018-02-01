<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace GraphPress\Controllers;

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use CapMousse\ReactRestify\Http\Session;
use Pho\Kernel\Kernel;
use Valitron\Validator;
use PhoNetworksAutogenerated\User;


/**
 * Takes care of Authentication
 * 
 * 10/10
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class AuthenticationController extends AbstractController 
{
    /**
     * Sign Up
     *
     * @score 10/10
     * 
     * @param Request $request
     * @param Response $response
     * @param Session $session
     * @param Kernel $kernel
     * @return void
     */
    public function signup(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['username', 'email', 'password']);
        $v->rule('email', 'email');
        if(!$v->validate()) {
            $this->fail($response, "Valid username, email and password required.");
            return;
        }
        if(!preg_match("/^[a-zA-Z0-9_]{1,12}$/", $data["username"])) {
            $this->fail($response, "Invalid username");
            return;
        }
        if(!preg_match("/[0-9A-Za-z!@#$%_]{5,15}/", $data["password"])) {
            $this->fail($response, "Invalid password");
            return;
        }
        $new_user = new User(
            $kernel, $kernel->graph(), $data["username"], $data["email"], $data["password"]
        );
        $session->set($request, "id", (string) $new_user->id());
        $response->writeJson([
            "success"=>true, 
            "id" => (string) $new_user->id()
        ])->end();
    }

    /**
     * Log In
     * 
     * @score 10/10
     *
     * @param Request $request
     * @param Response $response
     * @param Session $session
     * @param Kernel $kernel
     * @return void
     */
    public function login(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['username', 'password']);
        //$v->rule('email', 'email');
        if(!$v->validate()) {
            $this->fail($response, "Username and password fields are required.");
            return;
        }
        $result = $kernel->index()->query(
            "MATCH (n:user {Username: {username}, Password: {password}}) RETURN n",
            [ 
                "username" => $data["username"],
                "password" => md5($data["password"])
            ]
        );
        $success = (count($result->results()) == 1);
        if(!$success) {
            $this->fail($response, "Information don't match records");
            return;
        }
        $user = $result->results()[0];
        $session->set($request, "id", $user["udid"]);
        $this->succeed($response, ["id" => $user["udid"]]);
    }

    /**
     * Log Out
     * 
     * @score 10/10
     *
     * @param Request $request
     * @param Response $response
     * @param Session $session
     * @return void
     */
    public function logout(Request $request, Response $response, Session $session) 
    {
        $session->set($request, "id", null);
        $response->writeJson([
            "success"=> true
        ])->end();
    }

    /**
     * Who Am I?
     *
     * @score 10/10
     * 
     * @param Request $request
     * @param Response $response
     * @param Session $session
     * @return void
     */
    public function whoami(Request $request, Response $response, Session $session)
    {
        if(!is_null($id=$this->dependOnSession(...\func_get_args())))
            $this->succeed($response, ["id" => $id]);
    }

}