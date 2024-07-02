<?php

namespace billmn\turnstile;

use billmn\turnstile\models\Settings;
use billmn\turnstile\services\TurnstileService;
use billmn\turnstile\services\Validator;
use billmn\turnstile\services\Widget;
use billmn\turnstile\variables\TurnstileVariable;
use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\User;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;
use yii\base\ModelEvent;

/**
 * Turnstile plugin
 *
 * @method static Turnstile getInstance()
 * @method Settings getSettings()
 * @author Davide Bellini
 * @copyright Davide Bellini
 * @license MIT
 * @property-read TurnstileService $turnstileService
 * @property-read Validator $validator
 * @property-read Widget $widget
 */
class Turnstile extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'widget' => Widget::class,
                'validator' => Validator::class,
            ],
        ];
    }

    public function init()
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->registerTwigVariables();

            // Set up user registration hook.
            $settings = $this->getSettings();
            if ($settings->validateUserRegistrations && Craft::$app->getRequest()->getIsSiteRequest()) {
                Event::on(User::class, User::EVENT_BEFORE_VALIDATE, function (ModelEvent $event) {
                    /** @var User $user */
                    $user = $event->sender;

                    // Only validate Turnstile on new users
                    if ($user->id === null && $user->uid === null) {
                        $isValid = Turnstile::getInstance()->validator->verify();
                        if (!$isValid) {
                            $user->addError('turnstile', 'Please verify you are human.');
                            $event->isValid = false;
                        }
                    }
                });
            }
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('turnstile/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    protected function registerTwigVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                $event->sender->set('turnstile', TurnstileVariable::class);
            }
        );
    }
}
