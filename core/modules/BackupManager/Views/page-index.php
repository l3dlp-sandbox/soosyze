<div class="row">
    <div class="action_bar">
        <ul class="nav nav-tabs">
            <li>
                <a href="<?php echo $do_backup_route ?>"><?php echo t('Save'); ?></a>
            </li>
            <li>
                <a href="<?php echo $delete_all_route ?>" onclick="return confirm('<?php echo t('Delete all the backups ?') ?>');" style="color: red"><?php echo t('Delete all') ?></a>
            </li>
        </ul>
    </div>
    <fieldset class="responsive">
        <legend><?php echo t('Backups'); ?></legend>
        <?php if (empty($backups)) : ?>
            <h4 class="text-center"><?php echo t('There is no backup yet') ?></h4> 
        <?php else: ?>
            <table id="table-file" class="table table-hover">
                <thead>
                    <tr class="form-head">
                        <th data-tooltip="<?php echo t('The number of backup you did and the maximum number available') ?>">
                            <i class="fa fa fa-info-circle"></i> <?php echo count($backups) . ' / ' . ($max_backups ? $max_backups : '<i class="fa fa-infinity"></i>') ?>
                        </th>
                        <th><?php echo t('Date'); ?></th>
                        <th><?php echo t('Size'); ?></th>
                        <th><?php echo t('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $i => $backup) : ?>
                        <tr>
                            <th>
                                #<?php echo $i + 1; ?>
                            </th>
                            <td data-title="<?php echo t('Date'); ?>">
                                <span data-tooltip="<?php echo $backup['date']->format('Y-m-d H:i:s') ?>">
                                    <?php $date = Soosyze\Components\Util\Util::strHumansTimeDiff($backup['date'])?>
                                    <?php echo t($date[ 0 ], ['%s' => $date[ 1 ]]) ?>
                                </span> 
                            </td>
                            <td data-title="<?php echo t('Size'); ?>">   
                                <span data-tooltip="<?php echo $backup['size'] ?> octets">
                                    <?php echo \Soosyze\Components\Util\Util::strFileSizeFormatted($backup['size']); ?>
                                </span>
                            </td>
                            <td data-title="<?php echo t('Actions'); ?>">
                                <a data-tooltip="<?php echo t('Restore') ?>" 
                                   class="badge-role" 
                                   style="background-color: black; cursor: pointer"
                                   class="btn btn-action" 
                                   onclick="return confirm('<?php echo t('Restore the backup ?') ?>');" 
                                   href="<?php echo $backup['restore_link'] ?>"
                                >
                                    <i class="fa fa-trash-restore" aria-hidden="true"></i> 
                                </a>
                                <a data-tooltip="<?php echo t('Download') ?>" 
                                   class="badge-role" 
                                   style="background-color: black; cursor: pointer"
                                   class="btn btn-action" target="_blank" 
                                   href="<?php echo $backup['download_link'] ?>"
                                >
                                    <i class="fa fa-download" aria-hidden="true"></i>
                                </a>
                                <a  data-tooltip="<?php echo t('Delete') ?>" 
                                    class="badge-role" style="background-color: black; cursor: pointer"
                                    class="btn btn-action" 
                                    onclick="return confirm('<?php echo t('Delete the backup ?') ?>');" 
                                    href="<?php echo $backup['delete_link'] ?>"
                                >
                                    <i class="fa fa-times" aria-hidden="true"></i>
                                </a>
                            </td>
                        <tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
    </fieldset>
</div>