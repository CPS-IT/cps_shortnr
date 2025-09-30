<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\PlatformAdapter\FileSystem;

use Error;
use ParseError;
use Throwable;

interface FileSystemInterface
{
    /**
     * Checks whether a file or directory exists
     * @link https://php.net/manual/en/function.file-exists.php
     * @param string $filename <p>
     * Path to the file or directory.
     * </p>
     * <p>
     * On windows, use //computername/share/filename or
     * \\computername\share\filename to check files on
     * network shares.
     * </p>
     * @return bool true if the file or directory specified by
     * filename exists; false otherwise.
     * </p>
     * <p>
     * This function will return false for symlinks pointing to non-existing
     * files.
     * </p>
     * <p>
     * This function returns false for files inaccessible due to safe mode restrictions. However, these
     * files still can be included if
     * they are located in safe_mode_include_dir.
     * </p>
     * <p>
     * The check is done using the real UID/GID instead of the effective one.
     */
    public function file_exists(string $filename): bool;

    /**
     * Reads entire file into a string
     * @link https://php.net/manual/en/function.file-get-contents.php
     * @param string $filename <p>
     * Name of the file to read.
     * </p>
     * @param bool $use_include_path [optional] <p>
     * Note: As of PHP 5 the FILE_USE_INCLUDE_PATH constant can be
     * used to trigger include path search.
     * </p>
     * @param resource $context [optional] <p>
     * A valid context resource created with
     * stream_context_create. If you don't need to use a
     * custom context, you can skip this parameter by null.
     * </p>
     * @param int $offset [optional] <p>
     * The offset where the reading starts.
     * </p>
     * @param int|null $length [optional] <p>
     * Maximum length of data read. The default is to read until end
     * of file is reached.
     * </p>
     * @return string|false The function returns the read data or false on failure.
     */
    public function file_get_contents(string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): string|false;

    /**
     * Includes and evaluates the specified file
     *
     * This method wraps PHP's require statement to make it mockable for testing.
     * The file is included and evaluated as PHP code, and the return value of the
     * included file is returned.
     *
     * @param string $filename Path to the file to be included and evaluated
     * @return mixed The return value of the included file, typically an array for config files
     * @throws Error If the file contains a fatal error
     * @throws ParseError If the file contains a parse error
     * @throws Throwable Any exception thrown by the included file
     *
     * @link https://php.net/manual/en/function.require.php
     */
    public function require(string $filename): mixed;

    /**
     * Attempts to create the directory specified by pathname.
     * @link https://php.net/manual/en/function.mkdir.php
     * @param string $directory <p>
     * The directory path.
     * </p>
     * @param int $permissions [optional] <p>
     * The mode is 0777 by default, which means the widest possible
     * access. For more information on modes, read the details
     * on the chmod page.
     * </p>
     * <p>
     * mode is ignored on Windows.
     * </p>
     * <p>
     * Note that you probably want to specify the mode as an octal number,
     * which means it should have a leading zero. The mode is also modified
     * by the current umask, which you can change using
     * umask().
     * </p>
     * @param bool $recursive [optional] <p>
     * Allows the creation of nested directories specified in the pathname. Default to false.
     * </p>
     * @param resource $context [optional]
     * @return bool true on success or false on failure.
     */
    public function mkdir(string $directory, int $permissions = 0777, bool $recursive = false, mixed $context = null): bool;

    /**
     * Create file with unique file name
     * @link https://php.net/manual/en/function.tempnam.php
     * @param string $directory <p>
     * The directory where the temporary filename will be created.
     * </p>
     * @param string $prefix <p>
     * The prefix of the generated temporary filename.
     * </p>
     * Windows use only the first three characters of prefix.
     * @return string|false the new temporary filename, or false on
     * failure.
     */
    public function tempnam(string $directory, string $prefix): string|false;

    /**
     * Write a string to a file
     * @link https://php.net/manual/en/function.file-put-contents.php
     * @param string $filename <p>
     * Path to the file where to write the data.
     * </p>
     * @param mixed $data <p>
     * The data to write. Can be either a string, an
     * array or a stream resource.
     * </p>
     * <p>
     * If data is a stream resource, the
     * remaining buffer of that stream will be copied to the specified file.
     * This is similar with using stream_copy_to_stream.
     * </p>
     * <p>
     * You can also specify the data parameter as a single
     * dimension array. This is equivalent to
     * file_put_contents($filename, implode('', $array)).
     * </p>
     * @param int $flags [optional] <p>
     * The value of flags can be any combination of
     * the following flags (with some restrictions), joined with the binary OR
     * (|) operator.
     * </p>
     * <p>
     * <table>
     * Available flags
     * <tr valign="top">
     * <td>Flag</td>
     * <td>Description</td>
     * </tr>
     * <tr valign="top">
     * <td>
     * FILE_USE_INCLUDE_PATH
     * </td>
     * <td>
     * Search for filename in the include directory.
     * See include_path for more
     * information.
     * </td>
     * </tr>
     * <tr valign="top">
     * <td>
     * FILE_APPEND
     * </td>
     * <td>
     * If file filename already exists, append
     * the data to the file instead of overwriting it. Mutually
     * exclusive with LOCK_EX since appends are atomic and thus there
     * is no reason to lock.
     * </td>
     * </tr>
     * <tr valign="top">
     * <td>
     * LOCK_EX
     * </td>
     * <td>
     * Acquire an exclusive lock on the file while proceeding to the
     * writing. Mutually exclusive with FILE_APPEND.
     * </td>
     * </tr>
     * </table>
     * </p>
     * @param resource $context [optional] <p>
     * A valid context resource created with
     * stream_context_create.
     * </p>
     * @return int|false The function returns the number of bytes that were written to the file, or
     * false on failure.
     */
    public function file_put_contents(string $filename, mixed $data, int $flags = 0, mixed $context = null): int|false;

    /**
     * Renames a file or directory
     * @link https://php.net/manual/en/function.rename.php
     * @param string $from <p>
     * </p>
     * <p>
     * The old name. The wrapper used in oldname
     * must match the wrapper used in
     * newname.
     * </p>
     * @param string $to <p>
     * The new name.
     * </p>
     * @param resource $context [optional]
     * @return bool true on success or false on failure.
     */
    public function rename(string $from, string $to, mixed $context = null): bool;

    /**
     * Deletes a file
     * @link https://php.net/manual/en/function.unlink.php
     * @param string $filename <p>
     * Path to the file.
     * </p>
     * @param resource $context [optional]
     * @return bool true on success or false on failure.
     */
    function unlink(string $filename, mixed $context = null): bool;

    /**
     * Gets file modification time
     * @link https://php.net/manual/en/function.filemtime.php
     * @param string $filename <p>
     * Path to the file.
     * </p>
     * @return int|false the time the file was last modified, or false on failure.
     * The time is returned as a Unix timestamp, which is
     * suitable for the date function.
     */
    public function filemtime(string $filename): int|false;

    /**
     * List files and directories inside the specified path
     * @link https://php.net/manual/en/function.scandir.php
     * @param string $directory <p>
     * The directory that will be scanned.
     * </p>
     * @param int $sorting_order <p>
     * By default, the sorted order is alphabetical in ascending order. If
     * the optional sorting_order is set to non-zero,
     * then the sort order is alphabetical in descending order.
     * </p>
     * @param resource $context [optional] <p>
     * For a description of the context parameter,
     * refer to the streams section of
     * the manual.
     * </p>
     * @return array|false an array of filenames on success, or false on
     * failure. If directory is not a directory, then
     * boolean false is returned, and an error of level
     * E_WARNING is generated.
     */
    public function scandir(string $directory, int $sorting_order = 0, mixed $context = null): array|false;

    /**
     * Tells whether the filename is a regular file
     * @link https://php.net/manual/en/function.is-file.php
     * @param string $filename <p>
     * Path to the file.
     * </p>
     * @return bool true if the filename exists and is a regular file, false
     * otherwise.
     */
    public function is_file(string $filename): bool;
}
