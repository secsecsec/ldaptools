<?php
/**
 * This file is part of the LdapTools package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\LdapTools\Resolver;

use LdapTools\AttributeConverter\AttributeConverterInterface;
use LdapTools\Connection\LdapConnectionInterface;
use LdapTools\Object\LdapObject;
use LdapTools\Schema\LdapObjectSchema;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class AttributeValueResolverSpec extends ObjectBehavior
{
    protected $entryTo = [
        'username' => 'chad',
        'emailAddress' => 'Chad.Sikorra@gmail.com',
        'disabled' => false,
        'passwordMustChange' => true,
    ];

    protected $entryFrom = [
        'username' => 'chad',
        'emailAddress' => 'Chad.Sikorra@gmail.com',
        'disabled' => 512,
        'passwordMustChange' => 0,
    ];

    protected $expectedSearch = [
        '(&(distinguishedName=\63\6e\3d\66\6f\6f\2c\64\63\3d\66\6f\6f\2c\64\63\3d\62\61\72))',
        ['userAccountControl'],
        null,
        "subtree",
        null,
    ];

    protected $expectedResult = [
        'count' => 1,
        0 => [
            'userAccountControl' => [
                'count' => 1,
                0 => "512",
            ],
            'count' => 2,
            'dn' => "CN=foo,DC=foo,DC=bar",
        ],
    ];

    /**
     * @var LdapObjectSchema
     */
    protected $schema;

    /**
     * @var LdapConnectionInterface
     */
    protected $connection;

    /**
     * @param \LdapTools\Connection\LdapConnectionInterface $connection
     */
    function let($connection)
    {
        $schema = new LdapObjectSchema('ad', 'user');
        $schema->setAttributeMap([
            'username' => 'sAMAccountName',
            'emailAddress' => 'mail',
            'disabled' => 'userAccountControl',
            'passwordMustChange' => 'pwdLastSet',
            'passwordNeverExpires' => 'userAccountControl',
            'trustedForAllDelegation' => 'userAccountControl',
        ]);
        $schema->setConverterMap([
            'disabled' => 'user_account_control',
            'passwordMustChange' => 'password_must_change',
            'trustedForAllDelegation' => 'user_account_control',
            'passwordNeverExpires' => 'user_account_control',
        ]);
        $schema->setConverterOptions([
            'user_account_control' => [
                'uacMap' => [
                    'disabled' => '2',
                    'passwordNeverExpires' => '65536',
                    'smartCardRequired' => '262144',
                    'trustedForAllDelegation' => '524288',
                    'passwordIsReversible' => '128',
                ],
                'defaultValue' => '512',
            ]
        ]);
        $this->schema = $schema;
        $this->connection = $connection;
        $this->beConstructedThrough('getInstance', [$schema, $this->entryTo, AttributeConverterInterface::TYPE_CREATE]);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('LdapTools\Resolver\AttributeValueResolver');
    }

    function it_should_allow_setting_the_dn()
    {
        $this->setDn('cn=foo,dc=foo,dc=bar')->shouldBeNull();
    }

    function it_should_allow_setting_the_ldap_connection()
    {
        $this->setLdapConnection($this->connection);
    }

    function it_should_convert_values_to_ldap()
    {
        $this->toLdap()->shouldHaveKeyWithValue('userAccountControl','512');
        $this->toLdap()->shouldHaveKeyWithValue('username','chad');
        $this->toLdap()->shouldHaveKeyWithValue('emailAddress','Chad.Sikorra@gmail.com');
        $this->toLdap()->shouldHaveKeyWithValue('passwordMustChange','0');
    }

    function it_should_aggregate_properly_on_creation()
    {
        $entry = $this->entryTo;
        $entry['disabled'] = true;
        $entry['passwordNeverExpires'] = true;
        $entry['trustedForAllDelegation'] = true;

        $this->beConstructedWith($this->schema, $entry, AttributeConverterInterface::TYPE_CREATE);
        $this->toLdap()->shouldHaveKeyWithValue('userAccountControl','590338');
    }

    function it_should_aggregate_properly_on_modification()
    {
        $entry = $this->entryTo;
        $entry['disabled'] = true;
        $entry['passwordNeverExpires'] = true;
        $entry['trustedForAllDelegation'] = true;

        $this->connection->search(...$this->expectedSearch)->willReturn($this->expectedResult);
        $this->connection->getLdapType()->willReturn('ad');
        $this->connection->getEncoding()->willReturn('UTF-8');
        $this->beConstructedWith($this->schema, $entry, AttributeConverterInterface::TYPE_CREATE);
        $this->setLdapConnection($this->connection);
        $this->setDn('cn=foo,dc=foo,dc=bar');

        $this->toLdap()->shouldHaveKeyWithValue('userAccountControl','590338');
        $this->toLdap()->shouldHaveKeyWithValue('username','chad');
        $this->toLdap()->shouldHaveKeyWithValue('emailAddress','Chad.Sikorra@gmail.com');
        $this->toLdap()->shouldHaveKeyWithValue('passwordMustChange','0');
    }

    function it_should_convert_values_from_ldap()
    {
        $this->beConstructedWith($this->schema, $this->entryFrom, AttributeConverterInterface::TYPE_SEARCH_FROM);

        $this->fromLdap()->shouldHaveKeyWithValue('disabled', false);
        $this->fromLdap()->shouldHaveKeyWithValue('username','chad');
        $this->fromLdap()->shouldHaveKeyWithValue('emailAddress','Chad.Sikorra@gmail.com');
        $this->fromLdap()->shouldHaveKeyWithValue('passwordMustChange', true);
    }

    public function getMatchers()
    {
        return [
            'haveKeyWithValue' => function ($subject, $key, $value) {
                return isset($subject[$key]) && ($subject[$key] === $value);
            }
        ];
    }
}
