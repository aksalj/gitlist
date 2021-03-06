<?php

namespace GitList\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class MainController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $route = $app['controllers_factory'];

        $route->get('/', function() use ($app) {
            $repositories = $app['git']->getRepositories($app['git.repos']);

            return $app['twig']->render('index.twig', array(
                /*'repositories'   => $repositories, */'folders' => $this->_groupIntoFolders($repositories)
            ));
        })->bind('homepage');


        $route->get('/refresh', function(Request $request) use ($app ) {
            # Go back to calling page
            return $app->redirect($request->headers->get('Referer'));
        })->bind('refresh');

        $route->get('{repo}/stats/{branch}', function($repo, $branch) use ($app) {
            $repository = $app['git']->getRepositoryFromName($app['git.repos'], $repo);

            if ($branch === null) {
                $branch = $repository->getHead();
            }

            $stats = $repository->getStatistics($branch);
            $authors = $repository->getAuthorStatistics($branch);

            return $app['twig']->render('stats.twig', array(
                'repo'           => $repo,
                'branch'         => $branch,
                'branches'       => $repository->getBranches(),
                'tags'           => $repository->getTags(),
                'stats'          => $stats,
                'authors'        => $authors,
            ));
        })->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('branch', $app['util.routing']->getBranchRegex())
          ->value('branch', null)
          ->bind('stats');

        $route->get('{repo}/{branch}/rss/', function($repo, $branch) use ($app) {
            $repository = $app['git']->getRepositoryFromName($app['git.repos'], $repo);

            if ($branch === null) {
                $branch = $repository->getHead();
            }

            $commits = $repository->getPaginatedCommits($branch);

            $html = $app['twig']->render('rss.twig', array(
                'repo'           => $repo,
                'branch'         => $branch,
                'commits'        => $commits,
            ));

            return new Response($html, 200, array('Content-Type' => 'application/rss+xml'));
        })->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('branch', $app['util.routing']->getBranchRegex())
          ->value('branch', null)
          ->bind('rss');

        return $route;
    }

    private function _groupIntoFolders($repositories){
        $folders = array();
        foreach ($repositories as $repo){
            $parts = explode("/",$repo['name']);
            if(count($parts) > 1){
                $folder = $parts[0];
                $repo['display_name'] = $parts[1];

                if(array_key_exists($folder,$folders)){
                    array_push($folders[$folder]['repositories'],$repo);
                    $folders[$folder]['repo_count']++;
                }else{
                    $entry = array("folder_name" => str_replace(".git","",$folder), "repositories" => array($repo), "repo_count"=>1);
                    $folders[$folder] = $entry;
                }
            }else{
                $folder = $repo['name'];
                $repo['display_name'] = $folder;
                $folders[$folder] = array("folder_name" => str_replace(".git","",$folder), "repositories" => array($repo), "repo_count"=>1);
            }
        }
        return $folders;
    }
}
