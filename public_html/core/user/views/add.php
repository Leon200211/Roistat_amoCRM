<form id="main-form" class="vg-wrap vg-element vg-ninteen-of-twenty" method="post" action="<?=$this->adminPath . $this->action?>" enctype="multipart/form-data">


    <div class="vg-wrap vg-element vg-full vg-firm-background-color4 vg-box-shadow">

    <?php

            echo '<div class="vg-wrap vg-element">';

            // если не подключен шаблон
            if(!@include $_SERVER['DOCUMENT_ROOT'] . $this->formTemplates . 'inputForm.php'){
                throw new \core\base\exceptions\RouteException("Не найден шаблон " .
                    $_SERVER['DOCUMENT_ROOT'] . $this->formTemplates . 'inputForm.php');
            }

            echo "</div>";

    ?>

        <div class="vg-element vg-half vg-left">
            <div class="vg-element vg-padding-in-px">
                <input type="submit" class="vg-text vg-firm-color1 vg-firm-background-color4 vg-input vg-button" value="Отправить">
            </div>
        </div>

    </div>



</form>
