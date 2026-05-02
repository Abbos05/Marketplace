import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={` ${
                active
                    ? 'border-indigo-400 bg-indigo-50 text-indigo-700 focus:border-indigo-700 focus:bg-indigo-100 focus:text-indigo-800'
                    : 'border-transparent text-white-500 hover:border-white-300 hover:bg-gray-500 hover:text-white-800 focus:border-gray-300 focus:bg-gray-50 focus:text-gray-800'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
