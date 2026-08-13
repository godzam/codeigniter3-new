<img src="<?php echo $icon ?>" alt="<?php echo lang('memory_usage') ?>" title="<?php echo lang('memory_usage') ?>" /> <?php echo $memory ?>
<?php if (count($views_memory)): ?>
    <div class="detail memory_usage">
        <div class="scroll">
            <?php foreach ($views_memory as $path => $formatted): ?>
                <p>
                    <span class="left-col"><?php echo $path ?> :</span>
                    <span class="right-col"><?php echo $formatted ?></span>
                </p>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>
