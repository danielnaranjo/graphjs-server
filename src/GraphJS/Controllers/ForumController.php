<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace GraphJS\Controllers;

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use CapMousse\ReactRestify\Http\Session;
use Pho\Kernel\Kernel;
use Valitron\Validator;
use PhoNetworksAutogenerated\User;
use PhoNetworksAutogenerated\Thread;
use PhoNetworksAutogenerated\UserOut\Start;
use PhoNetworksAutogenerated\UserOut\Reply;
use Pho\Lib\Graph\ID;



/**
 * Takes care of Forum
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class ForumController extends AbstractController
{
    /**
     * Start Forum Thread 
     * 
     * [title, message]
     * 
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * @param string   $id
     * 
     * @return void
     */
    public function startThread(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['title', 'message']);
        $v->rule('lengthMax', ['title'], 80);
        if(!$v->validate()) {
            $this->fail($response, "Title (up to 80 chars) and Message are required.");
            return;
        }
        $i = $kernel->gs()->node($id);
        $thread = $i->start($data["title"], $data["message"]);
        $this->succeed(
            $response, [
            "id" => (string) $thread->id()
            ]
        );
    }

    /**
     * Reply Forum Thread
     * 
     * [id, message]
     *
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function replyThread(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['id', 'message']);
        if(!$v->validate()) {
            $this->fail($response, "Thread ID and Message are required.");
            return;
        }
        $i = $kernel->gs()->node($id);
        $thread = $kernel->gs()->node($data["id"]);
        if(!$thread instanceof Thread) {
            $this->fail($response, "Given  ID is not associated with a forum thread.");
            return;
        }
        $reply = $i->reply($thread, $data["message"]);
        $this->succeed(
            $response, [
            "id" => (string) $reply->id()
            ]
        );
    }


    /**
     * Get Threads
     * 
     * with number of replies
     *
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function getThreads(Request $request, Response $response, Kernel $kernel)
    {
        $threads = [];
        $everything = $kernel->graph()->members();
        
        foreach($everything as $thing) {
            if($thing instanceof Thread) {
                $threads[] = [
                    "id" => (string) $thing->id(),
                    "title" => $thing->getTitle(),
                    "author" => (string) $thing->edges()->in(Start::class)->current()->id(),
                    "timestamp" => (string) $thing->getCreateTime(),
                    "contributors" => 
                    array_map(
                        function(Reply $v): string 
                    {
                            return array_merge(
                                ["id"=>$v->getAuthor()->id()->toString()],
                                array_change_key_case(
                                    array_filter(
                                        $v->getAuthor()->attributes()->toArray(), 
                                        function (string $key): bool {
                                            return strtolower($key) != "password";
                                        },
                                        ARRAY_FILTER_USE_KEY
                                    ), CASE_LOWER
                                )
                                );
                    }, $thing->getReplies())
                ];
            }
        }
        $this->succeed(
            $response, [
            "threads" => $threads
            ]
        );
    }

    /**
     * Get Thread
     * 
     * [id]
     *
     * @param Request  $request
     * @param Response $response
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function getThread(Request $request, Response $response, Kernel $kernel)
    {
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['id']);
        if(!$v->validate()) {
            $this->fail($response, "Thread ID required.");
            return;
        }
        $thread = $kernel->gs()->node($data["id"]);
        if(!$thread instanceof Thread) {
            $this->fail($response, "Not a Thread");
        }
        $replies = $thread->getReplies();
        $this->succeed(
            $response, [
            "title" => $thread->getTitle(),
            "messages" => array_merge(
                [[
                    "author" => (string) ($thread->getAuthors()[0])->id(),
                    "content" => $thread->getContent()
                ]],
                array_map(
                    function ($obj): array {
                        return [
                            "author" => (string) $obj->tail()->id(),
                            "content" => $obj->getContent()
                        ];
                    },
                    $replies
                )
            )
            ]
        );
    }
}
