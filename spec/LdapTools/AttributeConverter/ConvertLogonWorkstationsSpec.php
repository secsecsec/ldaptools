<?php
/**
 * This file is part of the LdapTools package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\LdapTools\AttributeConverter;

use LdapTools\AttributeConverter\AttributeConverterInterface;
use LdapTools\BatchModify\Batch;
use LdapTools\Connection\LdapConnectionInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ConvertLogonWorkstationsSpec extends ObjectBehavior
{
    protected $connection;

    protected $expectedSearch = [
        '(&(distinguishedName=\63\6e\3d\66\6f\6f\2c\64\63\3d\66\6f\6f\2c\64\63\3d\62\61\72))',
        ['userWorkstations'],
        null,
        "subtree",
        null,
    ];

    protected $expectedResult = [
        'count' => 1,
        0 => [
            'userWorkstations' => [
                'count' => 1,
                0 => "foo,bar",
            ],
            'count' => 2,
            'dn' => "CN=foo,DC=foo,DC=bar",
        ],
    ];

    function let(LdapConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->setLdapConnection($connection);
        $this->setDn('cn=foo,dc=foo,dc=bar');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('LdapTools\AttributeConverter\ConvertLogonWorkstations');
    }

    function it_should_convert_a_string_of_comma_separated_workstations_to_an_array()
    {
        $this->fromLdap(['foo,bar'])->shouldBeEqualTo(['foo','bar']);
        $this->fromLdap(['foo'])->shouldBeEqualTo(['foo']);
    }

    function it_should_convert_an_array_of_workstations_to_a_comma_separated_list_for_ldap()
    {
        $this->setOperationType(AttributeConverterInterface::TYPE_CREATE);
        $this->toLdap(['foo','bar'])->shouldBeEqualTo('foo,bar');
    }

    function it_should_aggregate_values_when_converting_an_array_of_addresses_to_ldap_on_modification()
    {
        $this->connection->getLdapType()->willReturn('ad');
        $this->connection->search(...$this->expectedSearch)->willReturn($this->expectedResult);

        $this->setOperationType(AttributeConverterInterface::TYPE_MODIFY);
        $this->setBatch(new Batch(Batch::TYPE['ADD'],'logonWorkstations',['pc1']));
        $this->toLdap(['pc1'])->shouldBeEqualTo('foo,bar,pc1');
        $this->getBatch()->getModType()->shouldBeEqualTo(Batch::TYPE['REPLACE']);
        $this->setBatch(new Batch(Batch::TYPE['REMOVE'],'logonWorkstations',['foo']));
        $this->toLdap(['foo'])->shouldBeEqualTo('bar,pc1');
        $this->setBatch(new Batch(Batch::TYPE['REPLACE'],'',['bar', 'foo', 'test']));
        $this->toLdap(['bar', 'foo', 'test'])->shouldBeEqualTo('bar,foo,test');
    }

    function it_should_not_aggregate_values_on_a_search()
    {
        $this->setOperationType(AttributeConverterInterface::TYPE_SEARCH_FROM);
        $this->getShouldAggregateValues()->shouldBeEqualTo(false);
        $this->setOperationType(AttributeConverterInterface::TYPE_SEARCH_TO);
        $this->getShouldAggregateValues()->shouldBeEqualTo(false);
    }
}
