<?php
namespace Serfhos\MyRedirects\Controller;

use Serfhos\MyRedirects\Domain\Model\Redirect;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Backend Module Controller: Redirects
 *
 * @package Serfhos\MyRedirects\Controller
 */
class RedirectController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    /**
     * @var \Serfhos\MyRedirects\Domain\Repository\RedirectRepository
     * @inject
     */
    protected $redirectRepository;

    /**
     * @var \Serfhos\MyRedirects\Service\RedirectService
     * @inject
     */
    protected $redirectService;

    /**
     * @var \Serfhos\MyRedirects\Backend\BackendSession
     * @inject
     */
    protected $backendSession;

    /**
     * @var string
     */
    protected $sessionKey = 'MyRedirects';

    /**
     * Redirect request from post when forced
     *
     * @param \TYPO3\CMS\Extbase\Mvc\RequestInterface $request The request object
     * @param \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response The response, modified by this handler
     * @return void
     */
    public function processRequest(
        \TYPO3\CMS\Extbase\Mvc\RequestInterface $request,
        \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response
    ) {
        parent::processRequest($request, $response);

        if ($request instanceof \TYPO3\CMS\Extbase\Mvc\Web\Request) {
            $arguments = $request->getArguments();
            if (isset($arguments['forceRedirect']) && (bool) $arguments['forceRedirect'] === true) {
                unset ($arguments['forceRedirect'], $arguments['controller'], $arguments['action']);
                // Force array input
                if (isset($arguments['filter']) && !is_array($arguments['filter'])) {
                    $arguments['filter'] = array();
                }

                // Remove empty arguments
                $arguments = array_filter($arguments);
                $this->redirect($request->getControllerActionName(), null, null, $arguments);
            }
        }
    }

    /**
     * Initializes the view before invoking an action method
     *
     * @param \TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view The view to be initialized
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        $url = $this->uriBuilder->setAddQueryString(true)->setArgumentsToBeExcludedFromQueryString(array('returnUrl'))->build()
            . '&vC=' . urlencode($GLOBALS['BE_USER']->veriCode())
            . BackendUtility::getUrlToken('tceAction')
            . '&prErr=1&uPT=1';
        $view->assign('currentUrl', $url);
    }

    /**
     * Initializes the action
     *
     * @return void
     */
    protected function initializeAction()
    {
        parent::initializeAction();

        if (!isset($this->settings['staticTemplate'])) {
            $this->controllerContext = $this->buildControllerContext();
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.initialize.error.no_typoscript.description', 'my_redirects'),
                LocalizationUtility::translate('controller.initialize.error.no_typoscript.title', 'my_redirects')
            );
        } else {
            $this->backendSession
                ->setBackendUserAuthentication($GLOBALS['BE_USER'])
                ->createSession($this->sessionKey);

            $filters = $this->backendSession->getSessionContents($this->sessionKey);
            if ($filters === null) {
                $filters = array(
                    'filter' => array(),
                    'order' => 'url',
                    'direction' => QueryInterface::ORDER_ASCENDING
                );
            }

            if ($this->request->hasArgument('filter')) {
                $filters['filter'] = $this->request->getArgument('filter');
            }
            if ($this->request->hasArgument('order')) {
                $filters['order'] = $this->request->getArgument('order');
            }
            if ($this->request->hasArgument('direction')) {
                $filters['direction'] = $this->request->getArgument('direction');
            }

            $this->backendSession->saveSessionContents($filters);
        }
    }

    /**
     * Action: List redirects
     *
     * @return void
     */
    public function listAction()
    {
        $arguments = $this->backendSession->getSessionContents($this->sessionKey);
        $this->view->assignMultiple(array(
            'filter' => $arguments['filter'],
            'order' => $arguments['order'],
            'direction' => $arguments['direction'],
            'redirects' => $this->getRedirectRepository()->findByOrder(
                $arguments['filter'],
                $arguments['order'],
                $arguments['direction']
            ),
        ));
    }

    /**
     * Action: Create new redirect
     *
     * @param \Serfhos\MyRedirects\Domain\Model\Redirect $redirect
     * @param string $returnUrl
     * @return void
     */
    public function newAction($redirect = null, $returnUrl = '')
    {
        $this->view->assign('redirect', $redirect);

        if (!empty($returnUrl)) {
            $this->view->assign('returnUrl', $returnUrl);
        }
    }

    /**
     * Action: Check if redirect is still active and works as intended
     *
     * @param \Serfhos\MyRedirects\Domain\Model\Redirect $redirect
     * @param string $returnUrl
     * @return void
     */
    public function lookupAction($redirect = null, $returnUrl = '')
    {
        if ($redirect instanceof Redirect) {
            $this->getRedirectService()->activeLookup($redirect);
            $this->getRedirectRepository()->update($redirect);
        }

        if (!empty($returnUrl)) {
            $this->redirectToUri($returnUrl);
        } else {
            $this->redirect('list');
        }
    }

    /**
     * Action: Create new redirect in database and redirect to list
     *
     * @param \Serfhos\MyRedirects\Domain\Model\Redirect $redirect
     * @return void
     */
    public function createAction($redirect)
    {
        $this->addFlashMessage(
            LocalizationUtility::translate('controller.action.success.create.description', 'my_redirects'),
            LocalizationUtility::translate('controller.action.success.create.title', 'my_redirects')
        );
        $redirect->generateUrlHash();
        $this->getRedirectRepository()->add($redirect);
        $this->redirect('list');
    }

    /**
     * Action: Show edit form
     *
     * @param \Serfhos\MyRedirects\Domain\Model\Redirect $redirect
     * @param string $returnUrl
     * @return void
     */
    public function editAction($redirect = null, $returnUrl = '')
    {
        $this->view->assign('redirect', $redirect);

        if (!empty($returnUrl)) {
            $this->view->assign('returnUrl', $returnUrl);
        }
    }

    /**
     * Action: Update redirect in database and redirect to list
     *
     * @param \Serfhos\MyRedirects\Domain\Model\Redirect $redirect
     * @param string $returnUrl
     * @return void
     */
    public function updateAction($redirect = null, $returnUrl = '')
    {
        $this->addFlashMessage(
            LocalizationUtility::translate('controller.action.success.update.description', 'my_redirects'),
            LocalizationUtility::translate('controller.action.success.update.title', 'my_redirects')
        );

        $redirect->generateUrlHash();
        $this->getRedirectRepository()->update($redirect);

        if (!empty($returnUrl)) {
            $this->redirectToUri($returnUrl);
        } else {
            $this->redirect('list');
        }
    }

    /**
     * Action: Delete
     *
     * @param \Serfhos\MyRedirects\Domain\Model\Redirect $redirect
     * @param string $returnUrl
     * @return void
     */
    public function deleteAction($redirect = null, $returnUrl = '')
    {
        $this->addFlashMessage(
            LocalizationUtility::translate('controller.action.success.delete.description', 'my_redirects'),
            LocalizationUtility::translate('controller.action.success.delete.title', 'my_redirects')
        );
        $this->getRedirectRepository()->remove($redirect);

        if (!empty($returnUrl)) {
            $this->redirectToUri($returnUrl);
        } else {
            $this->redirect('list');
        }
    }

    /**
     * @return \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected function getObjectManager()
    {
        if (!isset($this->objectManager)) {
            $this->objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        }
        return $this->objectManager;
    }

    /**
     * @return \Serfhos\MyRedirects\Domain\Repository\RedirectRepository
     */
    protected function getRedirectRepository()
    {
        if (!isset($this->redirectRepository)) {
            $this->redirectRepository = $this->getObjectManager()->get('Serfhos\\MyRedirects\\Domain\\Repository\\RedirectRepository');
        }
        return $this->redirectRepository;
    }

    /**
     * @return \Serfhos\MyRedirects\Service\RedirectService
     */
    protected function getRedirectService()
    {
        if (!isset($this->redirectService)) {
            $this->redirectService = $this->getObjectManager()->get('Serfhos\\MyRedirects\\Service\\RedirectService');
        }
        return $this->redirectService;
    }

    /**
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUserAuthentication()
    {
        return $GLOBALS['BE_USER'];
    }
}