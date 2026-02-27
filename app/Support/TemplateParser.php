<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait TemplateParser
{
    public function addGeneralVariable(array $variables = []): array
    {
        $variables['app_name'] = config('config.team.name', config('config.general.app_name'));
        $variables['app_email'] = config('config.team.config.email', config('config.general.app_email'));
        $variables['app_phone'] = config('config.team.config.phone', config('config.general.app_phone'));
        $variables['app_address'] = Arr::toAddress([
            'address_line1' => config('config.team.config.address_line1', config('config.general.app_address_line1')),
            'address_line2' => config('config.team.config.address_line2', config('config.general.app_address_line2')),
            'city' => config('config.team.config.city', config('config.general.app_city')),
            'state' => config('config.team.config.state', config('config.general.app_state')),
            'country' => config('config.team.config.country', config('config.general.app_country')),
            'zipcode' => config('config.team.config.zipcode', config('config.general.app_zipcode')),
        ]);

        return $variables;
    }

    public function parseTemplate(Model $template, array $variables = [])
    {
        $variables = $this->addGeneralVariable($variables);

        foreach ($variables as $key => $variable) {
            $template->subject = Str::replace('##'.strtoupper($key).'##', $variable, $template->subject);
            $template->content = Str::replace('##'.strtoupper($key).'##', $variable, $template->content);
        }

        return $template;
    }

    public function parseMail(string $html = ''): string
    {
        $html = Str::of($html)->replaceMatches('/\[.*?\)/', function ($match) {
            if (Str::contains($match[0], '](')) {
                $text = Str::of($match[0])->after('[')->before('](');
                $url = Str::of($match[0])->after('](')->before(')');

                if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                    $url = '#';
                }

                // Foundation email template button
                // return '<table class="button rounded primary small-expanded">
                //     <tbody>
                //     <tr>
                //         <td>
                //         <table>
                //             <tbody>
                //             <tr>
                //                 <td><a href="'.$url.'">'.$text.'</a></td>
                //             </tr>
                //             </tbody>
                //         </table>
                //         </td>
                //     </tr>
                //     </tbody>
                // </table>';

                return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary">
                <tbody>
                  <tr>
                    <td align="left">
                      <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                        <tbody>
                          <tr>
                            <td> <a href="'.$url.'" target="_blank">'.$text.'</a> </td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                </tbody>
              </table>';
            }

            return $match[0];
        });

        return $html;
    }
}
