
<table id="table-file" class="table table-hover table-striped table-responsive file_manager-table" data-link_show="<?php echo $link_show; ?>">
    <thead>
        <tr>
            <th>&nbsp;</th>
            <th><?php echo t('Name'); ?></th>
            <th><?php echo t('Size'); ?></th>
            <th><?php echo t('Publishing date'); ?></th>
            <th><?php echo t('Actions'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if ($files): foreach ($files as $file): ?>

    <tr>
        <?php if ($file[ 'type' ] === 'dir'): ?>

        <th class="dir-link_show" data-link_show="<?php echo $file[ 'link_show' ]; ?>">
            <span class="file <?php echo $file[ 'ext' ]; ?>"></span>
        </th>
        <?php elseif ($file[ 'type' ] === 'image'): ?>

        <th class="file-link_show" data-link_show="<?php echo $file[ 'link_show' ]; ?>" data-toogle="modal" data-target="#modal_filemanager">
            <div class="file-link_show_img"
                 style="background-image: url('<?php echo $file[ 'link' ]; ?>')">
            </div>
        </th>
        <?php else: ?>

        <th class="file-link_show" data-link_show="<?php echo $file[ 'link_show' ]; ?>" data-toogle="modal" data-target="#modal_filemanager">
            <span class="file <?php echo $file[ 'ext' ]; ?>"><span class="ext-name"><?php echo $file[ 'ext' ]; ?></span></span>
        </th>
        <?php endif; ?>

        <td class="file-name" data-title="<?php echo t('Name'); ?>">
            <?php echo $file[ 'name' ]; ?><?php if ($file[ 'ext' ] !== 'dir'): ?><span class="ext">.<?php echo $file[ 'ext' ]; ?></span><?php endif; ?>

        </td>
        <td data-title="<?php echo t('Size'); ?>">
            <span data-tooltip="<?php echo $file[ 'size_octet' ]; ?> octets"><?php echo $file[ 'size' ]; ?></span>
        </td>
        <td data-title="<?php echo t('Publishing date'); ?>">
            <?php echo $file[ 'time' ]; ?>

        </td>
        <td class="actions-file">
            <div class="btn-actions" role="group" aria-label="action">
                <?php foreach ($file[ 'actions' ] as $action): ?>

                <a class="btn btn-action <?php echo $action[ 'class' ]; ?>"
                    href="<?php echo $action[ 'link' ]; ?>"
                    <?php if ($action[ 'class' ] === 'mod'): ?>
                    data-toogle="modal"
                    data-target="#modal_filemanager"
                    <?php endif; ?>
                    data-tooltip="<?php echo $action[ 'title_link' ]; ?>">
                    <i class="<?php echo $action[ 'icon' ]; ?>" aria-hidden="true"></i>
                </a>
                <?php endforeach; ?>

            </div>
        </td>
    </tr><?php endforeach; else: ?>

    <tr>
        <td colspan="5" class="alert alert-info">
            <div class="content-nothing">
                <i class="fa fa-inbox" aria-hidden="true"></i>
                <p><?php echo t('This directory does not currently contain any files.'); ?></p>
            </div>
        </td>
    </tr>
    <?php endif; ?>

    </tbody>
    <?php if ($files): ?>

    <tfoot>
        <tr>
            <td colspan="2">
                <?php
                echo t('@nb_dir folder(s), @nb_file file(s)', [
                    '@nb_dir'  => $nb_dir, '@nb_file' => $nb_file
                ]);
                ?>

            </td>
            <td colspan="3">
            <?php if ($profil[ 'folder_store' ] || $profil[ 'file_store' ]): ?>

                <span data-tooltip="<?php echo t('Total size / maximum data quota'); ?>">
                    <?php echo $size_all; ?>
                    <?php if ($profil[ 'folder_size' ] === 0): ?>
                        / <i class="fa fa-infinity" aria-hidden="true"></i>
                    <?php else: ?>
                        / <?php echo $profil[ 'folder_size' ]; ?>Mo
                    <?php endif; ?>

                </span>
            <?php else: ?>

                <span data-tooltip="Total size"><?php echo $size_all; ?></span>
            <?php endif; ?>

            </td>
        </tr>
    </tfoot>
    <?php endif; ?>

</table>