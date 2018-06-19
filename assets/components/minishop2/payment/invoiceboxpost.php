<?php

$html = '<form method="post" action="https://go.invoicebox.ru/module_inbox_auto.u" accept-charset="UTF-8" id="paymentform" name="paymentform">';
            foreach ($_GET as $key => $param) {
                $html.= '<input type="hidden" name="' . $key . '" value="' . $param . '">';
            }
            $html.= '</form>';
            $html.= '<br>
			<script type="text/javascript">document.getElementById("paymentform").submit();</script>
			';
			echo $html;
		die();