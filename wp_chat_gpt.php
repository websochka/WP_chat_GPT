<?php
/*
Plugin Name: Вопрос-ответ на базе ChatGPT
Plugin URI: https://sochka.com
Description: Вставьте шорткод [GPT_vopros] в любую страницу, где требуется вывести окно чата
Version: 0.1
Requires at least: 5.2
Author: Yaroslav Sochka
Author URI: https://sochka.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

//настройки плагина
add_action( 'admin_init', 'gpt_settings_chat_init' );

function gpt_settings_chat_init() {
	add_settings_section(
		'gpt_chat_section',
		'Настройки чата GPT',
		'gpt_setting_section_chat_function',
		'discussion'
	);
	add_settings_field(
		'gpt_chat_1',
		'API ключ GPT',
		'gpt_setting_chat_function',
		'discussion',
		'gpt_chat_section'
	);

		add_settings_field(
		'gpt_chat_2',
		'Температура (от 0 до 1)',
		'gpt_setting_chat_function2',
		'discussion',
		'gpt_chat_section'
	);


	register_setting( 'discussion', 'gpt_chat_1' );
	register_setting( 'discussion', 'gpt_chat_2' );
}


function gpt_setting_section_chat_function() {
    echo '<section id="gptchat">';
	echo '<p>Вставьте шорткод [GPT_vopros] в любую страницу для загрузки функционала</p>';
	echo '</section>';
}

function gpt_setting_chat_function() {
	?>
	<input
		name="gpt_chat_1"
		type="text"
		value="<?= esc_attr( get_option(  'gpt_chat_1' ) ) ?>"
	/>  <a href="https://platform.openai.com/account/api-keys" target="_blank" title="Требуется регистрация">Получить API</a>
					<?php 
}


function gpt_setting_chat_function2() {
	?>
<input name="gpt_chat_2" type="range" min="0" max="1" step="0.01" value="<?= esc_attr( get_option( 'gpt_chat_2' ) ) ?>" oninput="updateValue(this.value)">
<b><span id="gptvalue"><?= esc_attr( get_option( 'gpt_chat_2' ) ) ?></span></b>
<script>function updateValue(value) {document.getElementById("gptvalue").innerHTML = value;}</script>
 Чем выше, тем оригинальнее будут комментарии, но скорость генерации выше
	<?php
}

//ссылка на настройки плагина
function gptchat_plugin_links( $links, $file ) {
    if ( $file == plugin_basename( __FILE__ ) ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'options-discussion.php#gptchat' ) ) . '">Настройки</a>';
        array_push( $links, $settings_link );
    }
    return $links;
}
add_filter( 'plugin_action_links', 'gptchat_plugin_links', 10, 2 );


//генерируем GPT комментарий

function add_chat_to_user() {

parse_str($_POST['query'], $output);
$voprosgpt = $output['quers'];


$api_key_gpt = esc_attr( get_option(  'gpt_chat_1' ) );
$temperaturegpt =  esc_attr( get_option(  'gpt_chat_2' ) );

if (strlen($voprosgpt) > 5) {

 $ch = curl_init();
 $post_fields = array(
        "model" => "gpt-3.5-turbo",
        "messages" => array(
            array(
                "role" => "user",
                "content" => $voprosgpt
            )
        ),
        "max_tokens" => 1024,
        "top_p" => 1,
        "frequency_penalty" => 0,
        "presence_penalty" => 0,
        "stop" => ["\\n"],
        "temperature" => floatval($temperaturegpt)
    );

    $header  = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key_gpt
    ];

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
	
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
	 curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch);
    }
    curl_close($ch);

$result = json_decode($result, true);
echo $result['choices'][0]['message']['content'];

 wp_die();

   }  else { 
  echo 'Вы не задали вопрос';
}
 }
add_action('wp_ajax_add_chat_to_user', 'add_chat_to_user');
add_action('wp_ajax_nopriv_add_chat_to_user', 'add_chat_to_user');




// Регистрируем шорткод [GPT_vopros]
function gpt_vopros_shortcode() {
    // Добавляем стили и разметку
    $output = '<style>
                    #vopros {font-weight: 400; font-size: 22px;}
                    #results {
                        display: none;
                        min-height: 20px;
                        padding: 19px;
                        margin-bottom: 20px;
                        background-color: #f5f5f5;
                        border: 1px solid transparent;
                        border-radius: 3px;
                        box-shadow: inset 0 1px 1px rgba(0,0,0,0.05);
                    }
                </style>';
    $output .= '<div id="vopros"></div>';
    $output .= '<div id="results"></div>';
    $output .= '<script>
                    jQuery(document).ready(function($) {
                        $("#formx").on("submit", function(event) {
                            var msg = $("#formx").serialize();
                            $.ajax({
                                type: "POST",
                                url: ajaxurl,
                                cache: false,
                             //   timeout: 11000,
                                data: {
                                    action: "add_chat_to_user",
                                    query: msg
                                },
                                success: function(data) {
                                    $("#vopros").html($("textarea[name=quers]").val());
                                    $("textarea[name=quers]").val("");
                                    $("#results").html(data).show();
                                },
                                beforeSend: function(data) {
                                    $("#results").html("<p>Формирую ответ...</p>").show();
                                },
                                dataType: "html",
                                error:  function(data){
                                    $("#results").html("<p>Попробуйте позже</p>").show();
                                }
                            });
                        });
                    });
                </script>';
    $output .= '<form method="POST" id="formx" action="javascript:void(null);">
                    <textarea name="quers" value="" rows="4" cols="50" placeholder="Введите вопрос" style="width: 100%;"></textarea><br>
                    <button name="commit" type="submit" value="Задать вопрос" class="btn btn-default btn btn-primary">Задать вопрос</button>
                </form><br>';

    return $output;
}
add_shortcode( 'GPT_vopros', 'gpt_vopros_shortcode' );



add_action('wp_head', 'myplugin_ajaxurl');

function myplugin_ajaxurl() {

   echo '<script type="text/javascript">
           var ajaxurl = "' . admin_url('admin-ajax.php') . '";
         </script>';
}

?>
