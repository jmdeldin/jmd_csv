<?php
$plugin = array(
    'author' => 'Jon-Michael Deldin',
    'author_uri' => 'http://jmdeldin.com',
    'description' => 'Batch-import articles from a CSV',
    'type' => 1,
    'version' => '0.2',
);

if (0) {
?>

# --- BEGIN PLUGIN HELP ---

//inc <README>

# --- END PLUGIN HELP ---

<?php
}

# --- BEGIN PLUGIN CODE ---

if (txpinterface === 'admin')
{
    global $textarray;
    add_privs('jmd_csv', 1);
    register_callback('jmd_csv', 'jmd_csv');
    register_tab('extensions', 'jmd_csv', 'jmd_csv');
    // i18n
    $textarray = array_merge($textarray, array(
        'jmd_csv_file' => 'CSV file:',
        'jmd_csv_file_error' => 'Error reading CSV',
        'jmd_csv_import' => 'Import',
        'jmd_csv_import_csv' => 'Import CSV',
        'jmd_csv_imported' => 'CSV imported successfully.',
    ));
}

/**
 * Interface for the CSV import.
 *
 * @param string $event
 * @param string $step
 */
function jmd_csv($event, $step)
{
    global $jmd_csv, $file_base_path;
    ob_start('jmd_csv_head');
    $jmd_csv = new JMD_CSV();
    if ($step === 'import')
    {
        $file = gps('file');
        if ($file)
        {
            $handle = fopen($file_base_path . DS . $file, 'r');
            if ($handle)
            {
                $jmd_csv->import($handle, gps('status'));
                $msg = gTxt('jmd_csv_imported');
            }
            else
            {
                $msg = gTxt('jmd_csv_file_error');
            }
        }
    }
    pageTop('jmd_csv', (isset($msg) ? $msg : ''));

    $gTxt = 'gTxt';
    $out = <<<EOD
<fieldset id="jmd_csv">
    <legend>{$gTxt('jmd_csv_import_csv')}</legend>
    <div>
        <label>{$gTxt('jmd_csv_file')}
            {$jmd_csv->fileList()}
        </label>
    </div>
    <div>
        <label>{$gTxt('import_status')}
            {$jmd_csv->statusList()}
        </label>
    </div>
    <button type="submit">{$gTxt('jmd_csv_import')}</button>
</fieldset>
EOD;
    echo form($out . eInput('jmd_csv') . sInput('import'));
}

/**
 * Inserts CSS into the head.
 *
 * @param string $buffer
 */
function jmd_csv_head($buffer)
{
    $find = '</head>';
    $insert = <<<EOD
<style type="text/css">
#jmd_csv
{
    margin: 0 auto;
    padding: 0.5em;
    width: 50%;
}
    #jmd_csv legend
    {
        font-weight: 900;
    }
    #jmd_csv div
    {
        margin: 0 0 1em;
    }
</style>
EOD;

    return str_replace($find, $insert . $find, $buffer);
}


class JMD_CSV
{
    /**
     * Returns a select box of available CSVs.
     */
    public function fileList()
    {
        $files = safe_column('filename', 'txp_file',
            'category="jmd_csv"');
        if ($files)
        {
            $out = '<select name="file">';
            foreach ($files as $file)
            {
                $out .= '<option value="' . $file . '">' . $file . '</option>';
            }
            $out .= '</select>';

            return $out;
        }
    }

    /**
     * Returns a select box of article-statuses.
     */
    public function statusList()
    {
        $statuses = array(
            'draft' => 1,
            'hidden' => 2,
            'pending' => 3,
            'live' => 4,
            'sticky' => 5,
        );
        $out = '<select name="status">';
        foreach ($statuses as $key => $value)
        {
            $out .= '<option value="' . $value .'">' . gTxt($key) . '</option>';
        }
        $out .= '</select>';

        return $out;
    }

    /**
     * Reads a CSV and inserts it into the textpattern table.
     *
     * @param resource $handle File opened with fopen()
     * @param int $status Article status.
     */
    public function import($handle, $status)
    {
        global $prefs, $txp_user;
        $row = 1;
        while (($csv = fgetcsv($handle, 0, ',')) !== FALSE)
        {
            $fields = count($csv);
            if ($row === 1)
            {
                for ($i = 0; $i < $fields; $i++)
                {
                    $header[$i] = $csv[$i];
                }
            }
            else
            {
                $insert = '';
                foreach ($header as $key => $value)
                {
                    // escape all fields
                    $csv[$key] = doSlash($csv[$key]);
                    if ($value === 'Title')
                    {
                        $url_title = stripSpace($csv[$key], 1);
                    }
                    if ($value === 'Body' || $value === 'Excerpt')
                    {
                        $insert .= "{$value}_html='{$csv[$key]}',";
                    }
                    $insert .= "{$value}='{$csv[$key]}',";
                }
                $uid = md5(uniqid(rand(),true));
                $insert .= <<<EOD
AuthorID='{$txp_user}',
LastModID='{$txp_user}',
AnnotateInvite='{$prefs['comments_default_invite']}',
url_title='{$url_title}',
uid='{$uid}',
feed_time=now(),
Posted=now(),
LastMod=now(),
Status={$status},
textile_body=0,
textile_excerpt=0
EOD;
                safe_insert('textpattern', $insert);
            }
            $row++;
        }
    }
}

# --- END PLUGIN CODE ---
?>
