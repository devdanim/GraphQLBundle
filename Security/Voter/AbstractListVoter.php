<?php
/**
 * Date: 9/12/16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQLBundle\Security\Voter;


use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Youshido\GraphQLBundle\Security\Manager\SecurityManagerInterface;

abstract class AbstractListVoter extends Voter
{

    /** @var string[] */
    protected $list = [];

    /** @var bool */
    protected $enabled = false;

    protected function supports($attribute, $subject): bool
    {
        return $this->enabled && $attribute == SecurityManagerInterface::RESOLVE_ROOT_OPERATION_ATTRIBUTE;
    }

    protected function isLoggedInUser(TokenInterface $token)
    {
        return is_object($token->getUser());
    }

    /**
     * @param array $list
     */
    public function setList(array  $list)
    {
        $this->list = $list;
    }

    /**
     * @return \string[]
     */
    public function getList()
    {
        return $this->list;
    }

    protected function inList($query)
    {
        return in_array($query, $this->list);
    }

    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }
}
